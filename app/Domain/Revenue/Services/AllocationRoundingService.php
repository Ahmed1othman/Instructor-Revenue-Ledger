<?php

namespace App\Domain\Revenue\Services;

class AllocationRoundingService
{
    /**
     * @param  array<int, int>  $weights  instructor_id => engagement weight
     * @return array<int, int> instructor_id => allocated minor units
     */
    public function distribute(int $instructorPoolMinor, array $weights): array
    {
        if ($instructorPoolMinor === 0) {
            return array_map(fn (): int => 0, $weights);
        }

        if ($weights === []) {
            return [];
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight === 0) {
            return array_map(fn (): int => 0, $weights);
        }

        $floors = [];
        $remainders = [];

        foreach ($weights as $instructorId => $weight) {
            $numerator = $instructorPoolMinor * $weight;
            $floors[$instructorId] = intdiv($numerator, $totalWeight);
            $remainders[$instructorId] = $numerator % $totalWeight;
        }

        $allocated = $floors;
        $remaining = $instructorPoolMinor - array_sum($floors);

        if ($remaining > 0) {
            $ranked = array_keys($weights);

            usort($ranked, function (int $a, int $b) use ($remainders): int {
                if ($remainders[$a] !== $remainders[$b]) {
                    return $remainders[$b] <=> $remainders[$a];
                }

                return $a <=> $b;
            });

            for ($i = 0; $i < $remaining; $i++) {
                $instructorId = $ranked[$i];
                $allocated[$instructorId]++;
            }
        }

        return $allocated;
    }
}
