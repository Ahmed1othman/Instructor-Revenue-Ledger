@php
    $segments = $segments ?? [];
    $title = $title ?? null;
    $subtitle = $subtitle ?? null;
    $total = max(1, collect($segments)->sum('value'));
@endphp

<div class="space-y-3">
    @if ($title)
        <div>
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $title }}</h3>
            @if ($subtitle)
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    <div class="flex h-3 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
        @foreach ($segments as $segment)
            @if (($segment['value'] ?? 0) > 0)
                <div
                    class="{{ $segment['color'] ?? 'bg-gray-400' }}"
                    style="width: {{ max(1, (int) round(($segment['value'] * 100) / $total)) }}%"
                    title="{{ $segment['label'] ?? '' }}: {{ $segment['formatted'] ?? '' }}"
                ></div>
            @endif
        @endforeach
    </div>

    <div class="grid gap-2 sm:grid-cols-2">
        @foreach ($segments as $segment)
            @if (($segment['value'] ?? 0) > 0)
                <div class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                    <span class="inline-block h-2.5 w-2.5 rounded-full {{ $segment['color'] ?? 'bg-gray-400' }}"></span>
                    <span class="font-medium">{{ $segment['label'] ?? '' }}</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ $segment['formatted'] ?? '' }}</span>
                    @if (isset($segment['percent']))
                        <span class="text-gray-400">({{ $segment['percent'] }}%)</span>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</div>
