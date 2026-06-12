@php
    /** @var \App\Domain\Revenue\DTOs\SubscriptionFinancialSummary $summary */
    $summary = $getState();

    $steps = [
        ['label' => 'Paid by student', 'amount' => $summary->paidMinor, 'color' => 'border-sky-500'],
        ['label' => 'Earned so far', 'amount' => $summary->earnedMinor, 'color' => 'border-emerald-500'],
        ['label' => 'Allocated to instructors', 'amount' => $summary->instructorPoolAllocatedMinor, 'color' => 'border-indigo-500'],
        ['label' => 'Paid to instructors', 'amount' => $summary->instructorPaidMinor, 'color' => 'border-cyan-500'],
        ['label' => 'Outstanding to instructors', 'amount' => $summary->instructorOutstandingMinor, 'color' => 'border-amber-500'],
    ];

    if ($summary->refundedMinor > 0) {
        $steps[] = ['label' => 'Refunded to student', 'amount' => $summary->refundedMinor, 'color' => 'border-rose-500'];
    }
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="space-y-3">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Financial lifecycle</h3>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($steps as $step)
                <div class="rounded-lg border-l-4 {{ $step['color'] }} bg-gray-50 p-3 dark:bg-gray-900/40">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $step['label'] }}</p>
                    <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">
                        {{ \App\Domain\Money\Money::formatMinor($step['amount'], $summary->currency) }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</x-dynamic-component>
