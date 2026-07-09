<?php

namespace App\Services\Bloom;

/**
 * Largest-remainder apportionment: distributes an integer total across
 * weighted buckets such that the result always sums to exactly $total
 * (independent rounding of percentages can drift by 1-2 items).
 *
 * Used twice in the multi-lesson pipeline:
 *   1. Total exam items -> per-lesson quota (weighted by time/emphasis)
 *   2. Each lesson's quota -> per-Bloom's-level counts (weighted by TOS %)
 */
class ApportionmentService
{
    /**
     * @param array<string, float> $rawCounts Unrounded weighted counts, keyed by bucket name
     * @param int $total Target integer total
     * @return array<string, int>
     */
    public static function apportion(array $rawCounts, int $total): array
    {
        if (empty($rawCounts) || $total <= 0) {
            return array_map(fn () => 0, $rawCounts);
        }

        $floors = array_map('floor', $rawCounts);
        $remainders = [];
        foreach ($rawCounts as $key => $val) {
            $remainders[$key] = $val - $floors[$key];
        }

        $assigned = (int) array_sum($floors);
        $remaining = $total - $assigned;

        arsort($remainders);
        $keysByRemainder = array_keys($remainders);

        $result = $floors;

        if ($remaining > 0) {
            for ($i = 0; $i < $remaining; $i++) {
                $key = $keysByRemainder[$i % count($keysByRemainder)];
                $result[$key] += 1;
            }
        } elseif ($remaining < 0) {
            // Rare (weights don't sum to 1 cleanly): trim from smallest buckets
            $keysAscending = array_reverse($keysByRemainder);
            for ($i = 0; $i < abs($remaining); $i++) {
                $key = $keysAscending[$i % count($keysAscending)];
                if ($result[$key] > 0) {
                    $result[$key] -= 1;
                }
            }
        }

        return array_map('intval', $result);
    }
}
