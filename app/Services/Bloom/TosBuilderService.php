<?php

namespace App\Services\Bloom;

/**
 * Builds a balanced Table of Specification across MULTIPLE lessons.
 *
 * Two-level allocation:
 *   1. Total exam items are split across lessons, weighted by the
 *      teacher's stated emphasis (e.g. class hours/days spent on that lesson).
 *   2. Each lesson's item quota is then split across Bloom's Taxonomy
 *      levels using the standard TOS weight distribution.
 *
 * Both levels use largest-remainder apportionment, so counts always
 * sum exactly — per lesson AND in aggregate.
 */
class TosBuilderService
{
    /**
     * Default Bloom's-level balance, tunable per subject. Must sum to 1.0.
     */
    private const DEFAULT_BLOOM_WEIGHTS = [
        'Remembering' => 0.20,
        'Understanding' => 0.20,
        'Applying' => 0.20,
        'Analyzing' => 0.15,
        'Evaluating' => 0.15,
        'Creating' => 0.10,
    ];

    /**
     * @param array<int, array{title: string, weight: float}> $lessons Keyed by lesson index/id
     * @param int $totalItems
     * @param array|null $customBloomWeights
     * @return array{
     *   items_per_lesson: array<int|string, int>,
     *   levels_per_lesson: array<int|string, array<string, int>>,
     *   aggregate_distribution: array<string, array{weight: float, item_count: int}>
     * }
     */
    public function buildAcrossLessons(array $lessons, int $totalItems, ?array $customBloomWeights = null): array
    {
        $bloomWeights = $customBloomWeights ?? self::DEFAULT_BLOOM_WEIGHTS;

        // ---- Level 1: allocate total items across lessons by weight ----
        $totalWeight = array_sum(array_column($lessons, 'weight')) ?: 1;
        $rawPerLesson = [];
        foreach ($lessons as $key => $lesson) {
            $rawPerLesson[$key] = ($lesson['weight'] / $totalWeight) * $totalItems;
        }
        $itemsPerLesson = ApportionmentService::apportion($rawPerLesson, $totalItems);

        // ---- Level 2: allocate each lesson's quota across Bloom's levels ----
        $levelsPerLesson = [];
        $aggregate = array_fill_keys(array_keys($bloomWeights), 0);

        foreach ($itemsPerLesson as $key => $lessonTotal) {
            $rawLevels = [];
            foreach ($bloomWeights as $level => $w) {
                $rawLevels[$level] = $w * $lessonTotal;
            }
            $levelCounts = ApportionmentService::apportion($rawLevels, $lessonTotal);
            $levelsPerLesson[$key] = $levelCounts;

            foreach ($levelCounts as $level => $count) {
                $aggregate[$level] += $count;
            }
        }

        $aggregateDistribution = [];
        foreach ($bloomWeights as $level => $weight) {
            $aggregateDistribution[$level] = [
                'weight' => $weight,
                'item_count' => $aggregate[$level],
            ];
        }

        return [
            'items_per_lesson' => $itemsPerLesson,
            'levels_per_lesson' => $levelsPerLesson,
            'aggregate_distribution' => $aggregateDistribution,
        ];
    }
}