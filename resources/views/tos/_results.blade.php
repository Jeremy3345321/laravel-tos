{{-- resources/views/tos/_results.blade.php
     Extracted results markup — rendered both on first load (server-side)
     and re-rendered as an HTML fragment for AJAX swaps after create /
     generate-exam / lesson-retry, so there's only one source of truth
     for this markup. Expects: $tos (with lessons.objectives, lessons.examQuestions loaded) --}}

@php
    $bloomColor = [
        'Remembering'   => 'var(--bloom-remembering)',
        'Understanding' => 'var(--bloom-understanding)',
        'Applying'      => 'var(--bloom-applying)',
        'Analyzing'     => 'var(--bloom-analyzing)',
        'Evaluating'    => 'var(--bloom-evaluating)',
        'Creating'      => 'var(--bloom-creating)',
    ];
@endphp

<style>
    .tab-type { opacity: 0.75; margin-left: 0.4rem; }
    .mtf-verdict { margin: 0.4rem 0 0.2rem; font-size: 0.92rem; }
    .mtf-correction { margin: 0 0 0.4rem; font-size: 0.88rem; color: var(--ink-soft, #666); font-style: italic; }
    .enumeration-answers li { list-style: none; }
</style>

<div id="results-inner" data-tos-id="{{ $tos->id }}">

    <h1 class="subject-line">{{ $tos->course }}</h1>
    <p class="topic-line">{{ $tos->lessons->count() }} lesson(s) &middot; {{ $tos->total_items }} total items</p>

    <h2 class="section-title">Table of Specification &mdash; Overall</h2>
    <table class="tos-grid">
        <thead>
            <tr>
                <th>Bloom's Level</th>
                <th class="num">% Weight</th>
                <th class="num">Item Count</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tos->distribution as $level => $d)
                <tr style="--row-color: {{ $bloomColor[$level] ?? 'var(--paper-line)' }};">
                    <td class="level-name">{{ $level }}</td>
                    <td class="num">{{ isset($d['weight']) ? ($d['weight'] * 100) . '%' : '—' }}</td>
                    <td class="num">{{ $d['item_count'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td></td>
                <td class="num">{{ $tos->total_items }}</td>
            </tr>
        </tfoot>
    </table>

    <h2 class="section-title" style="margin-top: 2.5rem;">Lessons</h2>

    @foreach ($tos->lessons as $lesson)
        <div class="lesson-tos-block">
            <div class="lesson-tos-block-header">
                <span class="lesson-name">{{ $loop->iteration }}. {{ $lesson->title }}</span>
                <span class="lesson-meta">weight {{ $lesson->weight }} &middot; {{ $lesson->item_quota }} item(s)</span>
            </div>

            <div class="lesson-tos-block-body">

                <div class="bloom-legend" style="margin-bottom: 1.25rem;">
                    @foreach ($lesson->level_distribution as $level => $count)
                        @if (($count ?? 0) > 0)
                            <span class="bloom-chip">
                                <span class="dot" style="background: {{ $bloomColor[$level] ?? 'var(--paper-line)' }}"></span>
                                {{ $level }}: {{ $count }}
                            </span>
                        @endif
                    @endforeach
                </div>

                @if ($lesson->objectives->isNotEmpty())
                    <p class="field-hint" style="text-transform:uppercase; letter-spacing:0.06em; font-family: var(--font-mono); font-size:0.7rem; margin-bottom:0.6rem;">Classified Outcomes</p>
                    @foreach ($lesson->objectives as $obj)
                        <div class="tab-card" style="--row-color: {{ $bloomColor[$obj->bloom_level] ?? 'var(--paper-line)' }};">
                            <div class="tab-strip"></div>
                            <div class="tab-body">
                                <div class="tab-text">
                                    <p style="margin:0;">{{ $obj->objective_text }}</p>
                                    <span class="tab-level">{{ $obj->bloom_level }}</span>
                                </div>
                                <span class="confidence">{{ $obj->confidence }}% confident</span>
                            </div>
                        </div>
                    @endforeach
                @endif

                @if ($lesson->examQuestions->isNotEmpty())
                    <p class="field-hint" style="text-transform:uppercase; letter-spacing:0.06em; font-family: var(--font-mono); font-size:0.7rem; margin: 1.25rem 0 0.6rem;">
                        Generated Questions ({{ $lesson->examQuestions->count() }})
                    </p>
                    @php
                        $typeLabel = [
                            'multiple_choice' => 'Multiple Choice',
                            'modified_true_false' => 'Modified True or False',
                            'enumeration' => 'Enumeration',
                        ];
                        $typeOrder = [
                            'multiple_choice' => 0,
                            'modified_true_false' => 1,
                            'enumeration' => 2,
                        ];
                        $orderedQuestions = $lesson->examQuestions
                            ->sortBy(fn ($q) => $typeOrder[$q->question_type ?? 'multiple_choice'] ?? 99)
                            ->values();
                    @endphp
                    @foreach ($orderedQuestions as $i => $q)
                        @php $qType = $q->question_type ?? 'multiple_choice'; @endphp
                        <div class="question-card" style="--row-color: {{ $bloomColor[$q->bloom_level] ?? 'var(--paper-line)' }};">
                            <div class="tab-strip"></div>
                            <div class="question-body">
                                <div class="question-head">
                                    <p class="question-text">{{ $i + 1 }}. {{ $q->question }}</p>
                                    <span class="tab-level">{{ $q->bloom_level }}</span>
                                    <span class="tab-level tab-type">{{ $typeLabel[$qType] ?? $qType }}</span>
                                </div>

                                @if ($qType === 'multiple_choice')
                                    <ul class="options">
                                        @foreach ($q->options ?? [] as $letter => $option)
                                            <li class="{{ $letter === $q->correct_answer ? 'correct' : '' }}">
                                                {{ $letter }}. {{ $option }}
                                                @if ($letter === $q->correct_answer) &check; @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif ($qType === 'modified_true_false')
                                    <p class="mtf-verdict">
                                        Answer:
                                        <strong>{{ $q->is_true ? 'TRUE' : 'FALSE' }}</strong>
                                    </p>
                                    @if (!$q->is_true && $q->correction)
                                        <p class="mtf-correction">Correction: {{ $q->correction }}</p>
                                    @endif
                                @elseif ($qType === 'enumeration')
                                    <ul class="options enumeration-answers">
                                        @foreach ($q->accepted_answers ?? [] as $answer)
                                            <li class="correct">{{ $answer }} &check;</li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($q->rationale)
                                    <p class="rationale">{{ $q->rationale }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @elseif ($tos->examQuestions->isNotEmpty())
                    <div class="alert alert-error" style="margin-top: 1rem;">
                        <p style="margin: 0 0 0.6rem;">No questions were generated for this lesson yet &mdash; the PDF may not have had extractable text, or the request failed.</p>
                        <form class="lesson-retry-form" data-lesson-retry-form
                              data-action="{{ route('tos.generate-lesson-exam', [$tos, $lesson]) }}">
                            @csrf
                            <button type="submit" class="btn-seal btn-generate" style="padding: 0.5rem 1.1rem; font-size: 0.85rem;">
                                <span class="btn-label">Retry This Lesson</span>
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @if ($tos->examQuestions->isEmpty())
        <form id="generate-exam-form" data-action="{{ route('tos.generate-exam', $tos) }}">
            @csrf
            <button type="submit" class="btn-seal btn-generate" id="generate-exam-btn">
                <span class="btn-label">Generate Exam from All Lesson PDFs</span>
            </button>
            <p class="field-hint" id="generate-wait-hint" style="display:none; margin-top:0.75rem;">
                Generating questions for every lesson &mdash; this can take a minute or two. Please don't navigate away.
            </p>
        </form>
    @endif

</div>