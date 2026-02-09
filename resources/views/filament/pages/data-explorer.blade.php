<x-filament-panels::page>
    @if(!$tableId)
        <div class="flex flex-col items-center justify-center p-12 text-center border-2 border-dashed border-gray-300 rounded-xl dark:border-gray-700">
            <h2 class="text-xl font-bold">No Table Selected</h2>
            <p class="text-gray-500">Go to "Table Builder" and click "View Data" on a table.</p>
        </div>
    @else
        @if($isSpreadsheet)
            @php
                $dynamicModel = \App\Models\DynamicModel::find($tableId);
            @endphp
            @if($dynamicModel)
                @livewire(\App\Filament\Widgets\UniverSheetWidget::class, ['tableId' => $tableId])
            @endif
        @else
            {{ $this->table }}
        @endif
    @endif
</x-filament-panels::page>
