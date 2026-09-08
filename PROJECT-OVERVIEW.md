# Project Overview — Automated TOS & Exam Generator

A Laravel web app where a teacher uploads 1–15 lesson PDFs (each with a title, class-time
weight, and learning outcomes), and the system produces a balanced Table of Specification
plus a finished exam. Each lesson's outcomes are classified against Bloom's Taxonomy by a
built-in ML model, exam items are apportioned across lessons and cognitive levels with
exact-sum math, and questions are generated per lesson concurrently via Google Gemini —
every question traceable to its source lesson. Single-page Blade UI, no auth yet.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2, SQLite (default), Eloquent ORM
- **AI:** Google Gemini (`gemini-2.5-flash-lite`) via concurrent `Http::pool` with retry/backoff
- **ML:** Pure-PHP logistic-regression Bloom classifier (`php artisan bloom:train`); PDF parsing via `smalot/pdfparser`
- **Frontend:** Blade templates, Tailwind CSS v4, Vite, vanilla JS `fetch`
- **Tooling:** PHPUnit (SQLite in-memory), Laravel Pint, Pail, concurrently dev runner

## Task List

> Rule: when a task is completed, check its box and append the completion date
> `(done YYYY-MM-DD)`. When a completed task is modified later, update the date to the
> latest change. Items marked `(predates tracking)` were finished before this file existed.

- [x] Core TOS pipeline (predates tracking)
 - [x] Multi-lesson upload + validation (predates tracking)
 - [x] Per-lesson Bloom classification (predates tracking)
 - [x] Two-level apportionment across lessons and Bloom levels (predates tracking)
 - [x] TOS review UI with per-lesson + aggregate breakdown (predates tracking)
- [x] Exam generation (predates tracking)
 - [x] Concurrent per-lesson Gemini calls (predates tracking)
 - [x] Three question types with auto-mix per Bloom level (predates tracking)
 - [x] Sequential retry/backoff + missing-level backfill (predates tracking)
 - [x] Single-lesson retry route (predates tracking)
- [ ] Fix known issues
 - [ ] Add `question_type`, `is_true`, `correction`, `accepted_answers` to `ExamQuestion` `$fillable` + casts
 - [ ] Add `doctrine/dbal` to `composer.json` (required by question-type migration)
 - [ ] Add `GEMINI_API_KEY` to `.env.example` and fix stale `ANTHROPIC_API_KEY` README references
- [ ] Improve classifier accuracy
 - [ ] Collect real DepEd lesson objectives as labeled training data
 - [ ] Retrain model with expanded dataset
 - [ ] Add evaluation harness (holdout accuracy + per-class precision/recall) and a PHPUnit accuracy test
- [ ] New features
 - [ ] OCR support for scanned/image-only PDFs
 - [ ] Per-lesson Bloom weight override
 - [ ] Progress indicator during multi-lesson generation
 - [ ] Auth + per-teacher history scoping (`user_id` column already exists)
