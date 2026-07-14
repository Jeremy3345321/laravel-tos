<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\TableOfSpecification;
use App\Services\Bloom\BloomClassifierService;
use App\Services\Bloom\ExamGeneratorService;
use App\Services\Bloom\PdfTextExtractorService;
use App\Services\Bloom\TosBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TosController extends Controller
{
    public function __construct(
        private BloomClassifierService $classifier,
        private TosBuilderService $tosBuilder,
        private PdfTextExtractorService $pdfExtractor,
        private ExamGeneratorService $examGenerator,
    ) {}

    /**
     * Single-page entry point. Renders the create form (no $tos) or a
     * direct/shared link to an already-built TOS (with $tos) — both use
     * the exact same view, and every action from here on happens via
     * fetch() against the JSON endpoints below, never a full navigation.
     */
    public function create()
    {
        return view('tos.index');
    }

    public function show(Request $request, TableOfSpecification $tos)
    {
        $tos->load('lessons.objectives', 'lessons.examQuestions');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'html' => view('tos._results', ['tos' => $tos])->render(),
                'tos_id' => $tos->id,
                'course' => $tos->course,
                'has_exam' => $tos->examQuestions()->exists(),
            ]);
        }

        return view('tos.index', compact('tos'));
    }

    /**
     * Lightweight JSON list of past TOS records for the chat sidebar
     * history. No pagination for now — add ->limit(50) if this grows large.
     * Not scoped to a user yet (no auth wired in), but the user_id column
     * is already on the table — add ->where('user_id', auth()->id()) here
     * once auth is in place so teachers only see their own history.
     */
    public function history()
    {
        $items = TableOfSpecification::query()
            ->latest()
            ->get(['id', 'course', 'total_items', 'created_at'])
            ->map(fn (TableOfSpecification $tos) => [
                'id' => $tos->id,
                'course' => $tos->course,
                'total_items' => $tos->total_items,
                'created_at' => $tos->created_at->format('M j, Y'),
            ]);

        return response()->json(['items' => $items]);
    }

    /**
     * Step 1: Teacher submits multiple lessons (each with its own title,
     * weight/emphasis, learning outcomes, and PDF). Runs the classifier
     * per-lesson, then builds a two-level balanced TOS: items across
     * lessons (by weight) THEN across Bloom's levels (within each lesson).
     */
    public function classifyAndBuildTos(Request $request)
    {
        $validated = $request->validate([
            'course' => 'required|string|max:255',
            'total_items' => 'required|integer|min:5|max:200',
            'lessons' => 'required|array|min:1|max:15',
            'lessons.*.title' => 'required|string|max:255',
            'lessons.*.weight' => 'required|numeric|min:0.1|max:100',
            'lessons.*.objectives_text' => 'required|string',
            'lessons.*.pdf' => 'required|file|mimes:pdf|max:20480',
        ]);
        // Note: Laravel automatically returns a 422 JSON error response for
        // ajax/`Accept: application/json` requests on validation failure —
        // no extra handling needed here for that case.

        $lessonWeights = [];
        foreach ($validated['lessons'] as $i => $lessonInput) {
            $lessonWeights[$i] = ['title' => $lessonInput['title'], 'weight' => (float) $lessonInput['weight']];
        }

        $allocation = $this->tosBuilder->buildAcrossLessons($lessonWeights, (int) $validated['total_items']);

        $tos = DB::transaction(function () use ($request, $validated, $allocation) {
            $tos = TableOfSpecification::create([
                'course' => $validated['course'],
                'total_items' => $validated['total_items'],
                'distribution' => $allocation['aggregate_distribution'],
            ]);

            foreach ($validated['lessons'] as $i => $lessonInput) {
                $path = $request->file("lessons.$i.pdf")->store('lesson_pdfs');

                $lesson = $tos->lessons()->create([
                    'title' => $lessonInput['title'],
                    'weight' => $lessonInput['weight'],
                    'pdf_path' => $path,
                    'item_quota' => $allocation['items_per_lesson'][$i],
                    'level_distribution' => $allocation['levels_per_lesson'][$i],
                    'sort_order' => $i,
                ]);

                $objectives = collect(explode("\n", $lessonInput['objectives_text']))
                    ->map(fn ($line) => trim($line))
                    ->filter()
                    ->values()
                    ->all();

                $classified = $this->classifier->classifyMany($objectives);

                foreach ($classified as $item) {
                    $lesson->objectives()->create([
                        'tos_id' => $tos->id,
                        'objective_text' => $item['objective'],
                        'bloom_level' => $item['level'],
                        'bloom_level_index' => $item['level_index'],
                        'confidence' => $item['confidence'],
                        'all_probabilities' => $item['all_probabilities'],
                    ]);
                }
            }

            return $tos;
        });

        $status = 'TOS built across '.count($validated['lessons']).' lesson(s). Review the balance, then generate the exam.';

        return $this->respond($request, $tos, $status);
    }

    /**
     * Step 2: Extract each lesson's PDF text, then generate exam questions
     * for ALL lessons CONCURRENTLY (one API call per lesson, fired via
     * Http::pool - see ExamGeneratorService), each scoped to that lesson's
     * own item quota and Bloom's-level breakdown.
     */
    public function generateExam(Request $request, TableOfSpecification $tos)
    {
        // Generating for multiple lessons, each potentially retrying up to
        // 3x with backoff on rate-limit responses, can legitimately take
        // several minutes. The default 60s max_execution_time was killing
        // this mid-request and losing ALL lessons' results, not just the
        // one being retried. 300s gives real headroom; adjust if you add
        // more lessons per TOS or more retry attempts in ExamGeneratorService.
        set_time_limit(300);

        if ($tos->examQuestions()->exists()) {
            return $this->respond($request, $tos, 'Exam already generated for this TOS.');
        }

        $tos->load('lessons');

        $lessonJobs = [];
        $skipped = [];

        foreach ($tos->lessons as $lesson) {
            $absolutePath = Storage::path($lesson->pdf_path);
            $lessonText = $this->pdfExtractor->extractFromPath($absolutePath);
            $lessonText = $this->pdfExtractor->truncateForPrompt($lessonText, 10000);

            if (trim($lessonText) === '') {
                $skipped[] = $lesson->title;
                continue;
            }

            $lessonJobs[$lesson->id] = [
                'lesson_text' => $lessonText,
                'level_counts' => $lesson->level_distribution,
                'lesson_title' => $lesson->title,
            ];
        }

        if (empty($lessonJobs)) {
            return $this->respondError(
                $request,
                'Could not extract text from any lesson PDF. Scanned documents need OCR first.'
            );
        }

        $results = $this->examGenerator->generateForLessons($lessonJobs, $tos->course);
        $errors = $this->persistLessonResults($tos, $results);

        $status = 'Exam generated across '.count($results).' lesson(s).';
        if (!empty($skipped)) {
            $status .= ' Skipped (no extractable text): '.implode(', ', $skipped).'. You can retry a lesson individually once its PDF is fixed.';
        }

        return $this->respond($request, $tos, $status, $errors);
    }

    /**
     * Retry generation for a SINGLE lesson — used when the initial batch
     * run skipped a lesson (unreadable PDF) or that lesson's API call
     * failed while its siblings succeeded. Leaves every other lesson's
     * questions untouched.
     */
    public function generateExamForLesson(Request $request, TableOfSpecification $tos, Lesson $lesson)
    {
        set_time_limit(300);

        if ($lesson->tos_id !== $tos->id) {
            abort(404);
        }

        if ($lesson->examQuestions()->exists()) {
            return $this->respond($request, $tos, 'This lesson already has generated questions.');
        }

        $absolutePath = Storage::path($lesson->pdf_path);
        $lessonText = $this->pdfExtractor->extractFromPath($absolutePath);
        $lessonText = $this->pdfExtractor->truncateForPrompt($lessonText, 10000);

        if (trim($lessonText) === '') {
            return $this->respondError(
                $request,
                "\"{$lesson->title}\": still no extractable text in this PDF. If it's a scanned document, it needs OCR before this will work."
            );
        }

        $lessonJobs = [
            $lesson->id => [
                'lesson_text' => $lessonText,
                'level_counts' => $lesson->level_distribution,
                'lesson_title' => $lesson->title,
            ],
        ];

        $results = $this->examGenerator->generateForLessons($lessonJobs, $tos->course);
        $errors = $this->persistLessonResults($tos, $results);

        if (!empty($errors)) {
            return $this->respond($request, $tos, null, $errors);
        }

        return $this->respond($request, $tos, "Questions generated for \"{$lesson->title}\".");
    }

    /**
     * Shared save step for both the full-batch and single-lesson generation
     * paths. Returns any per-lesson error messages encountered.
     *
     * @param array<int, array{questions: array, error: ?string}> $results
     * @return string[]
     */
    private function persistLessonResults(TableOfSpecification $tos, array $results): array
    {
        $errors = [];

        foreach ($results as $lessonId => $result) {
            if ($result['error']) {
                $errors[] = $result['error'];
                continue;
            }

            $lesson = $tos->lessons->firstWhere('id', $lessonId) ?? Lesson::find($lessonId);
            if (!$lesson) {
                continue;
            }

            foreach ($result['questions'] as $q) {
                $type = $q['question_type'] ?? 'multiple_choice';

                $attrs = [
                    'tos_id' => $tos->id,
                    'bloom_level' => $q['bloom_level'],
                    'question_type' => $type,
                    'question' => $q['question'],
                    'rationale' => $q['rationale'] ?? null,
                ];

                switch ($type) {
                    case 'modified_true_false':
                        $attrs['is_true'] = (bool) ($q['is_true'] ?? false);
                        $attrs['correction'] = $q['is_true'] ? null : ($q['correction'] ?? null);
                        break;
                    case 'enumeration':
                        $attrs['accepted_answers'] = $q['accepted_answers'] ?? [];
                        break;
                    case 'multiple_choice':
                    default:
                        $attrs['options'] = $q['options'] ?? null;
                        $attrs['correct_answer'] = $q['correct_answer'] ?? null;
                        break;
                }

                $lesson->examQuestions()->create($attrs);
            }
        }

        return $errors;
    }

    /**
     * Builds the common response: JSON + rendered results fragment for
     * fetch()-driven requests (the normal path from the single-page view),
     * or a classic redirect for any client that isn't sending our AJAX
     * headers (progressive-enhancement fallback, e.g. JS disabled).
     */
    private function respond(Request $request, TableOfSpecification $tos, ?string $status = null, array $errors = []): mixed
    {
        $tos->load('lessons.objectives', 'lessons.examQuestions');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'html' => view('tos._results', ['tos' => $tos])->render(),
                'tos_id' => $tos->id,
                'status' => $status,
                'errors' => $errors,
            ], empty($errors) ? 200 : 207); // 207: partial success, some lessons still errored
        }

        $redirect = redirect()->route('tos.show', $tos);
        if ($status) {
            $redirect->with('status', $status);
        }
        if (!empty($errors)) {
            $redirect->withErrors($errors);
        }

        return $redirect;
    }

    private function respondError(Request $request, string $message): mixed
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['errors' => ['lesson_pdf' => $message]], 422);
        }

        return back()->withErrors(['lesson_pdf' => $message]);
    }
}