@php
    /** @var \App\Domain\Revenue\DTOs\SubscriptionFinancialSummary $summary */
    $summary = $getState();
    $segments = \App\Filament\Support\FinancialSegmentBuilder::paymentLifecycle($summary);
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @include('filament.components.segmented-bar', [
        'title' => 'Payment lifecycle',
        'subtitle' => 'Original payment: ' . \App\Domain\Money\Money::formatMinor($summary->paidMinor, $summary->currency),
        'segments' => $segments,
    ])
</x-dynamic-component>
