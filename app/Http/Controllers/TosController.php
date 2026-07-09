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

class TosController extends Controller
{
    public function __construct(
        private BloomClassifierService $classifier,
        private TosBuilderService $tosBuilder,
        private PdfTextExtractorService $pdfExtractor,
        private ExamGeneratorService $examGenerator,
    ) {}

    public function create()
    {
        return view('tos.create');
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

        // Build lightweight weight list for the allocator
        $lessonWeights = [];
        foreach ($validated['lessons'] as $i => $lessonInput) {
            $lessonWeights[$i] = ['title' => $lessonInput['title'], 'weight' => (float) $lessonInput['weight']];
        }

        $allocation = $this->tosBuilder->buildAcrossLessons($lessonWeights, (int) $validated['total_items']);

        $record = DB::transaction(function () use ($request, $validated, $allocation) {
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

        return redirect()->route('tos.show', $record)->with('status', 'TOS built across '.count($validated['lessons']).' lesson(s). Review the balance, then generate the exam.');
    }

    public function show(TableOfSpecification $tos)
    {
        $tos->load('lessons.objectives', 'lessons.examQuestions');

        return view('tos.show', compact('tos'));
    }

    /**
     * Step 2: Extract each lesson's PDF text, then generate exam questions
     * for ALL lessons CONCURRENTLY (one API call per lesson, fired via
     * Http::pool - see ExamGeneratorService), each scoped to that lesson's
     * own item quota and Bloom's-level breakdown.
     */
    public function generateExam(TableOfSpecification $tos)
    {
        if ($tos->examQuestions()->exists()) {
            return back()->with('status', 'Exam already generated for this TOS.');
        }

        $tos->load('lessons');

        $lessonJobs = [];
        $skipped = [];

        foreach ($tos->lessons as $lesson) {
            $absolutePath = \Illuminate\Support\Facades\Storage::path($lesson->pdf_path);
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
            return back()->withErrors([
                'lesson_pdf' => 'Could not extract text from any lesson PDF. Scanned documents need OCR first.',
            ]);
        }

        $results = $this->examGenerator->generateForLessons($lessonJobs, $tos->course);

        $errors = [];
        foreach ($results as $lessonId => $result) {
            if ($result['error']) {
                $errors[] = $result['error'];
                continue;
            }

            $lesson = $tos->lessons->firstWhere('id', $lessonId);
            foreach ($result['questions'] as $q) {
                $lesson->examQuestions()->create([
                    'tos_id' => $tos->id,
                    'bloom_level' => $q['bloom_level'],
                    'question' => $q['question'],
                    'options' => $q['options'],
                    'correct_answer' => $q['correct_answer'],
                    'rationale' => $q['rationale'] ?? null,
                ]);
            }
        }

        $status = 'Exam generated across '.count($results).' lesson(s).';
        if (!empty($skipped)) {
            $status .= ' Skipped (no extractable text): '.implode(', ', $skipped).'.';
        }

        $redirect = redirect()->route('tos.show', $tos)->with('status', $status);

        if (!empty($errors)) {
            $redirect->withErrors($errors);
        }

        return $redirect;
    }
}