<x-filament-widgets::widget>
    <x-filament::section :heading="$title" :description="$description">
        @include('filament.components.segmented-bar', [
            'segments' => $segments,
        ])
    </x-filament::section>
</x-filament-widgets::widget>
