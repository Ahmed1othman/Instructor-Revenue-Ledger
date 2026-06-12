@php
    /** @var \App\Domain\Revenue\DTOs\SubscriptionFinancialSummary $summary */
    $summary = $getState();
    $earnedSegments = \App\Filament\Support\FinancialSegmentBuilder::earnedRevenueSplit($summary);
    $poolSegments = \App\Filament\Support\FinancialSegmentBuilder::instructorPoolUtilization($summary);
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="space-y-6">
        @include('filament.components.segmented-bar', [
            'title' => 'Earned revenue split',
            'subtitle' => 'Earned so far: ' . \App\Domain\Money\Money::formatMinor($summary->earnedMinor, $summary->currency),
            'segments' => $earnedSegments,
        ])

        @if (count($poolSegments) > 0)
            @include('filament.components.segmented-bar', [
                'title' => 'Instructor pool utilization',
                'subtitle' => 'Contractual pool: ' . \App\Domain\Money\Money::formatMinor($summary->instructorPoolMinor, $summary->currency),
                'segments' => $poolSegments,
            ])
        @endif

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Total platform retained:
            <span class="font-medium text-gray-700 dark:text-gray-300">
                {{ \App\Domain\Money\Money::formatMinor($summary->totalPlatformRetainedMinor, $summary->currency) }}
            </span>
        </p>
    </div>
</x-dynamic-component>
