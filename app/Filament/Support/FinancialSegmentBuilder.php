<?php

namespace App\Filament\Support;

use App\Domain\Money\Money;
use App\Domain\Revenue\DTOs\SubscriptionFinancialSummary;

final class FinancialSegmentBuilder
{
    /**
     * @return array<int, array{label: string, value: int, color: string, formatted: string}>
     */
    public static function paymentLifecycle(SubscriptionFinancialSummary $summary): array
    {
        $total = max($summary->paidMinor, 1);
        $currency = $summary->currency;

        return array_values(array_filter([
            self::segment('Earned', $summary->earnedMinor, $total, 'bg-emerald-500', $currency),
            self::segment('Unearned', $summary->unearnedMinor, $total, 'bg-sky-500', $currency),
            self::segment('Refunded', $summary->refundedMinor, $total, 'bg-rose-500', $currency),
            $summary->remainingRefundableMinor > 0
                ? self::segment('Remaining refundable', $summary->remainingRefundableMinor, $total, 'bg-amber-400', $currency)
                : null,
        ]));
    }

    /**
     * @return array<int, array{label: string, value: int, color: string, formatted: string}>
     */
    public static function earnedRevenueSplit(SubscriptionFinancialSummary $summary): array
    {
        $total = max($summary->earnedMinor, 1);
        $currency = $summary->currency;

        return array_values(array_filter([
            self::segment('Platform contractual share', $summary->platformContractualShareMinor, $total, 'bg-indigo-500', $currency),
            self::segment('Instructor allocated', $summary->instructorPoolAllocatedMinor, $total, 'bg-emerald-500', $currency),
            self::segment('Unallocated instructor pool', $summary->unallocatedInstructorPoolMinor, $total, 'bg-amber-500', $currency),
        ], fn (array $segment): bool => $segment['value'] > 0));
    }

    /**
     * @return array<int, array{label: string, value: int, color: string, formatted: string}>
     */
    public static function instructorPoolUtilization(SubscriptionFinancialSummary $summary): array
    {
        $total = max($summary->instructorPoolMinor, 1);
        $currency = $summary->currency;

        return array_values(array_filter([
            self::segment('Allocated to instructors', $summary->instructorPoolAllocatedMinor, $total, 'bg-emerald-500', $currency),
            self::segment('Unallocated (no engagement)', $summary->unallocatedInstructorPoolMinor, $total, 'bg-amber-500', $currency),
        ], fn (array $segment): bool => $segment['value'] > 0));
    }

    /**
     * @return array<int, array{label: string, value: int, color: string, formatted: string, step: int}>
     */
    public static function instructorPayoutLifecycle(SubscriptionFinancialSummary $summary): array
    {
        $currency = $summary->currency;

        return array_values(array_filter([
            [
                'label' => 'Allocated to instructors',
                'value' => $summary->instructorPoolAllocatedMinor,
                'color' => 'bg-emerald-500',
                'formatted' => Money::formatMinor($summary->instructorPoolAllocatedMinor, $currency),
                'step' => 1,
            ],
            [
                'label' => 'Paid to instructors',
                'value' => $summary->instructorPaidMinor,
                'color' => 'bg-sky-500',
                'formatted' => Money::formatMinor($summary->instructorPaidMinor, $currency),
                'step' => 2,
            ],
            [
                'label' => 'Outstanding to instructors',
                'value' => $summary->instructorOutstandingMinor,
                'color' => 'bg-amber-500',
                'formatted' => Money::formatMinor($summary->instructorOutstandingMinor, $currency),
                'step' => 3,
            ],
            $summary->refundedMinor > 0 ? [
                'label' => 'Refunded to student',
                'value' => $summary->refundedMinor,
                'color' => 'bg-rose-500',
                'formatted' => Money::formatMinor($summary->refundedMinor, $currency),
                'step' => 4,
            ] : null,
        ]));
    }

    /**
     * @return array{label: string, value: int, color: string, formatted: string}
     */
    /**
     * @return array{label: string, value: int, color: string, formatted: string, percent: int}
     */
    private static function segment(
        string $label,
        int $value,
        int $total,
        string $color,
        string $currency,
    ): array {
        return [
            'label' => $label,
            'value' => $value,
            'color' => $color,
            'formatted' => Money::formatMinor($value, $currency),
            'percent' => $total > 0 ? (int) round(($value * 100) / $total) : 0,
        ];
    }

    /**
     * @param  array<int, array{label: string, value: int, color: string, formatted: string}>  $segments
     * @return array<int, array{label: string, value: int, color: string, formatted: string, percent: int}>
     */
    public static function withPercents(array $segments, int $total): array
    {
        $denominator = max($total, 1);

        return array_map(function (array $segment) use ($denominator): array {
            $segment['percent'] = (int) round(($segment['value'] * 100) / $denominator);

            return $segment;
        }, $segments);
    }
}
