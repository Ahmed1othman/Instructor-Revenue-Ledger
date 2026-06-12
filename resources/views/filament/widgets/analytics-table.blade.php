<x-filament-widgets::widget>
    <x-filament::section :heading="$heading" :description="$description ?? null">
        @if (count($rows) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">No records to display.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            @foreach ($columns as $column)
                                <th class="px-2 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                    {{ $column }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                @foreach ($row as $cell)
                                    <td class="px-2 py-2 text-gray-800 dark:text-gray-200">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
