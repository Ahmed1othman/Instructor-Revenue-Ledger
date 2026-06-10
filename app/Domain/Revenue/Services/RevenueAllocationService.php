<?php

namespace App\Domain\Revenue\Services;

use App\Models\LessonConsumption;
use App\Models\SettlementPeriod;
use App\Models\Subscription;
use Illuminate\Support\Collection;

class RevenueAllocationService
{
    public function __construct(
        private readonly AllocationRoundingService $roundingService,
    ) {}

    /**
     * @return array<int, int> instructor_id => weight (valid_watched_seconds)
     */
    public function engagementWeights(Subscription $subscription, SettlementPeriod $period): array
    {
        return LessonConsumption::query()
            ->where('subscription_id', $subscription->id)
            ->whereBetween('consumed_at', [
                $period->period_start->startOfDay(),
                $period->period_end->endOfDay(),
            ])
            ->selectRaw('instructor_id, SUM(valid_watched_seconds) as total_weight')
            ->groupBy('instructor_id')
            ->pluck('total_weight', 'instructor_id')
            ->map(fn ($weight): int => (int) $weight)
            ->all();
    }

    /**
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    public function allocatePool(int $instructorPoolMinor, array $weights): array
    {
        return $this->roundingService->distribute($instructorPoolMinor, $weights);
    }

    public function totalEngagementWeight(array $weights): int
    {
        return array_sum($weights);
    }
}
