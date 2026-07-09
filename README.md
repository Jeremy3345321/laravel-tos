# Automated TOS & Exam Generator — Multi-PDF Prototype (v2)

Now handles **5-10 lesson PDFs per course** with accuracy and speed, instead
of a single PDF.

## What changed from v1

v1 sent one giant PDF's text into one LLM call. That doesn't scale:
context gets diluted across many pages ("lost in the middle"), there's no
per-lesson traceability, and coverage across lessons is uneven.

v2 treats **each lesson PDF as its own scoped job**:

```
Teacher adds N lesson panels (title + weight + outcomes + PDF each)
        |
        v
BloomClassifierService classifies each lesson's outcomes independently
        |
        v
TosBuilderService — TWO-LEVEL allocation:
  1. total_items -> per-lesson quota, weighted by lesson "weight"
     (teacher-set: e.g. class hours/days spent on that lesson)
  2. each lesson's quota -> per-Bloom's-level counts (TOS % balance)
  (both levels use largest-remainder apportionment: always sums exactly)
        |
        v
   [Teacher reviews per-lesson + aggregate TOS, confirms balance]
        |
        v
PdfTextExtractorService extracts each lesson's PDF separately
        |
        v
ExamGeneratorService fires ONE Anthropic API call PER LESSON,
CONCURRENTLY via Http::pool() — 10 lessons finish in roughly the
time of 1-2 sequential calls, not 10x. No queue worker needed.
        |
        v
   Exam assembled, grouped by lesson, each question traceable
   to its source lesson.
```

## Why this is more accurate

- Each LLM call only sees ONE lesson's text — no dilution, no "lost in
  the middle." Prompts stay small and focused.
- Item counts are guaranteed to sum correctly at both the per-lesson level
  and the aggregate level (tested standalone before packaging).
- Every question is tagged with its source lesson, so you can verify
  coverage — e.g. confirm Lesson 7 wasn't skipped.
- Weighting lessons by time spent (not just page count) means a short-but-
  heavily-taught lesson gets proportionally more exam items than a long
  lesson you only briefly covered — matches how real TOS weighting works.

## Files changed/added since v1

```
app/Services/Bloom/
  ApportionmentService.php     - NEW: shared largest-remainder rounding, used twice
  TosBuilderService.php        - REWRITTEN: two-level (lesson + Bloom) allocation
  ExamGeneratorService.php     - REWRITTEN: concurrent per-lesson generation via Http::pool
  BloomClassifierService.php   - unchanged
  BloomTrainingData.php        - unchanged
  PdfTextExtractorService.php  - unchanged (already per-file)

app/Models/
  Lesson.php                   - NEW: one row per uploaded lesson PDF
  LearningObjective.php        - UPDATED: now belongs to a lesson
  ExamQuestion.php              - UPDATED: now belongs to a lesson
  TableOfSpecification.php     - UPDATED: hasMany lessons

app/Http/Controllers/
  TosController.php            - REWRITTEN: multi-lesson upload/classify/generate

database/migrations/
  2026_07_08_000001_create_tos_exam_tables.php - REWRITTEN: adds `lessons` table

resources/views/tos/
  create.blade.php             - REWRITTEN: repeatable lesson panels (add/remove via JS)
  show.blade.php                - REWRITTEN: per-lesson + aggregate TOS, exam grouped by lesson
```

## Setup (fresh migration required — schema changed)

Since the table structure changed (added `lessons`, and `learning_objectives`
/ `exam_questions` now require `lesson_id`), you need to reset the DB tables
for this feature:

```
php artisan migrate:fresh
```

⚠️ `migrate:fresh` drops ALL tables in the database, not just these ones.
If you have other data in this database you want to keep, instead just drop
the 4 tables manually in phpMyAdmin (`exam_questions`, `learning_objectives`,
`lessons`, `table_of_specifications`) and run `php artisan migrate`.

Everything else (composer packages, `.env`, `ANTHROPIC_API_KEY`,
`storage/app/ml/bloom_model.json`) stays the same as before — no need to
redo those steps.

## Using it

1. Visit `/tos/create`.
2. Enter the Course name and Total Exam Items.
3. For each lesson: title, weight (e.g. "3" for 3 class hours spent),
   paste that lesson's learning outcomes (one per line), upload that
   lesson's PDF. Click "+ Add Lesson" for each additional lesson (5-10 is fine).
4. Submit → review the TOS (aggregate + per-lesson breakdown).
5. Click "Generate Exam from All Lesson PDFs" — all lessons generate
   concurrently in one request.

## Performance notes

- **Speed**: with 10 lessons, expect roughly the latency of 1-2 sequential
  Claude calls (concurrent pool), not 10x. Actual wall-clock time still
  depends on each lesson's text length and item count.
- **Cost**: you still pay per-lesson API calls (10 lessons = 10 calls), just
  not sequentially slower. Cost doesn't change vs. calling one-by-one, only
  time does.
- **Rounding drift**: because allocation happens in two independent passes
  (lesson-level, then Bloom-level within lesson), the aggregate Bloom
  balance can drift by 1-2 items from the exact target percentages. This is
  normal and shown in testing to stay within 1 item — acceptable for
  real TOS use, but worth knowing about.

## Known limitations / next steps

- **Scanned PDFs** still need OCR first (reuse your existing Tesseract +
  Poppler pipeline from the HR project if any lesson PDFs are scanned).
- **No per-lesson Bloom weight override** — all lessons currently use the
  same global Bloom's % balance (20/20/20/15/15/10). If some lessons should
  emphasize higher-order thinking more than others, that'd need a per-lesson
  weight override field (straightforward addition if you want it).
- **No progress indicator during generation** — for 10 concurrent lessons,
  consider adding a loading state in the UI since the request may take
  10-30+ seconds depending on lesson length.