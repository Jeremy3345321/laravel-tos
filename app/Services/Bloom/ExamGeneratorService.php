<?php

namespace App\Services\Bloom;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Generates exam questions from lesson content, constrained to match
 * a Table of Specification's per-Bloom's-level item counts.
 *
 * Uses the Google Gemini API (free tier). Set GEMINI_API_KEY in your .env.
 * (config/services.php should have: 'gemini' => ['key' => env('GEMINI_API_KEY')])
 */
class ExamGeneratorService
{
    private const MODEL = 'gemini-2.5-flash-lite';
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':generateContent';

    /**
     * Single-lesson generation (kept for backward compatibility / simple use).
     *
     * @param string $lessonText Extracted PDF lesson content
     * @param array $tosDistribution Level => ['item_count' => int, ...]
     * @return array{questions: array, raw_response: ?string}
     */
    public function generate(string $lessonText, array $tosDistribution, string $subject = 'General'): array
    {
        $apiKey = $this->apiKey();
        $prompt = $this->buildPrompt($lessonText, $tosDistribution, $subject);

        $response = Http::withHeaders($this->headers($apiKey))
            ->timeout(120)
            ->post(self::API_URL, $this->body($prompt));

        if ($response->failed()) {
            throw new RuntimeException('Exam generation API call failed: ' . $response->body());
        }

        $text = $this->extractText($response->json());
        $questions = $this->parseQuestionsJson($text);

        return [
            'questions' => $questions,
            'raw_response' => $text,
        ];
    }

    /**
     * Generate exam questions for MULTIPLE lessons concurrently, one Gemini
     * API call per lesson, fired in parallel via Http::pool.
     *
     * @param array $lessonJobs Keyed by lesson_id => [
     *     'lesson_text' => string,
     *     'level_counts' => array (Bloom level => ['item_count' => int, ...]),
     *     'lesson_title' => string,
     * ]
     * @param string $course Course/subject name, used in the prompt
     * @return array Keyed by lesson_id => ['questions' => array, 'error' => ?string]
     */
    public function generateForLessons(array $lessonJobs, string $course = 'General'): array
    {
        $apiKey = $this->apiKey();

        $lessonIds = array_keys($lessonJobs);

        $responses = Http::pool(function ($pool) use ($lessonJobs, $apiKey, $course, $lessonIds) {
            $requests = [];
            foreach ($lessonIds as $lessonId) {
                $job = $lessonJobs[$lessonId];
                $prompt = $this->buildPrompt(
                    $job['lesson_text'],
                    $job['level_counts'],
                    $course,
                    $job['lesson_title'] ?? null
                );

                $requests[] = $pool->withHeaders($this->headers($apiKey))
                    ->timeout(120)
                    ->post(self::API_URL, $this->body($prompt));
            }

            return $requests;
        });

        $results = [];

        foreach ($lessonIds as $index => $lessonId) {
            $response = $responses[$index];

            try {
                if ($response instanceof \Throwable) {
                    throw $response;
                }

                if ($response->failed()) {
                    throw new RuntimeException('API call failed: ' . $response->body());
                }

                $text = $this->extractText($response->json());
                $questions = $this->parseQuestionsJson($text);

                $job = $lessonJobs[$lessonId];
                $questions = $this->backfillMissingLevels(
                    $questions,
                    $job['level_counts'],
                    $job['lesson_text'],
                    $course,
                    $job['lesson_title'] ?? null,
                    $apiKey
                );

                $results[$lessonId] = [
                    'questions' => $questions,
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                $title = $lessonJobs[$lessonId]['lesson_title'] ?? "Lesson {$lessonId}";
                $results[$lessonId] = [
                    'questions' => [],
                    'error' => "{$title}: " . $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Compares generated questions against the requested per-level counts.
     * If any Bloom's level came back short (or missing entirely), fires a
     * small follow-up request asking ONLY for the missing items and merges
     * the results in. Runs at most 2 extra passes to avoid infinite loops
     * if the model keeps refusing a level.
     */
    private function backfillMissingLevels(
        array $questions,
        array $levelCounts,
        string $lessonText,
        string $course,
        ?string $lessonTitle,
        string $apiKey
    ): array {
        for ($pass = 0; $pass < 2; $pass++) {
            $have = collect($questions)->countBy('bloom_level');

            $missing = collect($levelCounts)
                ->filter(fn ($d) => ($d['item_count'] ?? 0) > 0)
                ->mapWithKeys(fn ($d, $level) => [
                    $level => max(0, ($d['item_count'] ?? 0) - ($have[$level] ?? 0)),
                ])
                ->filter(fn ($n) => $n > 0);

            if ($missing->isEmpty()) {
                break;
            }

            $missingBreakdown = $missing
                ->map(fn ($n, $level) => "- {$level}: {$n} item(s)")
                ->implode("\n");

            $titleLine = $lessonTitle ? "LESSON: {$lessonTitle}\n" : '';

            $prompt = <<<PROMPT
You are an expert exam item writer for Philippine DepEd classrooms, following Bloom's Taxonomy.

COURSE/SUBJECT: {$course}
{$titleLine}
LESSON CONTENT:
---
{$lessonText}
---

A previous pass failed to generate questions for these specific Bloom's Taxonomy levels. Generate ONLY these now, exactly this many, with NO exceptions and NO empty results:
{$missingBreakdown}

Even for higher-order levels like Analyzing, Evaluating, or Creating on basic content, write your best-effort question that reasonably applies, compares, judges, or extends the lesson concepts. Do not skip any requested level.

Return ONLY valid JSON, no markdown fences, matching:
{
  "questions": [
    {
      "bloom_level": "...",
      "question": "...",
      "options": {"A": "...", "B": "...", "C": "...", "D": "..."},
      "correct_answer": "A",
      "rationale": "..."
    }
  ]
}
PROMPT;

            try {
                $response = Http::withHeaders($this->headers($apiKey))
                    ->timeout(120)
                    ->post(self::API_URL, $this->body($prompt));

                if ($response->failed()) {
                    break;
                }

                $text = $this->extractText($response->json());
                $extra = $this->parseQuestionsJson($text);

                if (empty($extra)) {
                    break;
                }

                $questions = array_merge($questions, $extra);
            } catch (\Throwable $e) {
                break;
            }
        }

        return $questions;
    }

    private function apiKey(): string
    {
        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            throw new RuntimeException('GEMINI_API_KEY is not configured in .env / config/services.php');
        }

        return $apiKey;
    }

    private function headers(string $apiKey): array
    {
        return [
            'x-goog-api-key' => $apiKey,
            'content-type' => 'application/json',
        ];
    }

    private function body(string $prompt): array
    {
        return [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens' => 4096,
                'responseMimeType' => 'application/json',
            ],
        ];
    }

    private function extractText(?array $data): string
    {
        $parts = collect($data['candidates'] ?? [])->first()['content']['parts'] ?? [];

        $text = collect($parts)->pluck('text')->implode("\n");

        if (trim($text) === '') {
            throw new RuntimeException('Empty response from Gemini API: ' . json_encode($data));
        }

        return $text;
    }

    private function buildPrompt(string $lessonText, array $levelCounts, string $subject, ?string $lessonTitle = null): string
    {
        $itemBreakdown = collect($levelCounts)
            ->filter(fn ($d) => ($d['item_count'] ?? 0) > 0)
            ->map(fn ($d, $level) => "- {$level}: {$d['item_count']} item(s)")
            ->implode("\n");

        $totalItems = collect($levelCounts)->sum('item_count');

        $titleLine = $lessonTitle ? "LESSON: {$lessonTitle}\n" : '';

        return <<<PROMPT
You are an expert exam item writer for Philippine DepEd classrooms, following Bloom's Taxonomy Table of Specification requirements.

COURSE/SUBJECT: {$subject}
{$titleLine}
LESSON CONTENT (source material to base questions on):
---
{$lessonText}
---

Generate exactly {$totalItems} multiple-choice exam questions based ONLY on the lesson content above, distributed EXACTLY as follows across Bloom's Taxonomy levels:
{$itemBreakdown}

Rules:
1. Each question must genuinely require thinking at its assigned Bloom's level (e.g. "Analyzing" questions must require breaking down/comparing/relating ideas, not just recall).
2. Each question must have exactly 4 options (A-D), one correct answer, and be answerable strictly from the lesson content provided.
3. Do not repeat concepts across questions unnecessarily; spread coverage across the lesson.
4. IMPORTANT: You must generate the exact requested count for EVERY Bloom's level listed, even for higher levels like Analyzing, Evaluating, or Creating on introductory/definitional content. If the lesson content is basic, write higher-level questions that ask students to apply, compare, judge the usefulness of, or extend the concepts in the lesson (e.g. "Which scenario would benefit most from X over Y?" for Evaluating, or "Design a modification to X that would achieve Y" for Creating). Do NOT return an empty "questions" array and do NOT skip any level under any circumstance — always produce your best-effort question for every requested item.
5. Return ONLY valid JSON (no markdown fences, no preamble, no explanation) matching this exact structure:

{
  "questions": [
    {
      "bloom_level": "Remembering",
      "question": "...",
      "options": {"A": "...", "B": "...", "C": "...", "D": "..."},
      "correct_answer": "A",
      "rationale": "short reason why this tests this Bloom's level"
    }
  ]
}
PROMPT;
    }

    private function parseQuestionsJson(string $text): array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(json)?/', '', $clean);
        $clean = preg_replace('/```$/', '', $clean);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['questions'])) {
            throw new RuntimeException('Failed to parse exam questions JSON from model response.');
        }

        return $decoded['questions'];
    }
}