<x-filament-panels::page>
    <form wire:submit="runQuery">
        {{ $this->form }}
    </form>

    @if($message)
        <div class="mt-4 p-4 bg-success-500/10 text-success-600 rounded-lg border border-success-500/20">
            {{ $message }}
        </div>
    @endif

    @if($results !== null && count($results) > 0)
        <x-filament::section>
            <x-slot name="heading">
                Query Results ({{ count($results) }} rows)
            </x-slot>

            @if(count($results) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                @foreach(array_keys((array)$results[0]) as $column)
                                    <th scope="col" class="px-6 py-3">
                                        {{ $column }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results as $row)
                                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                    @foreach((array)$row as $value)
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if(is_array($value) || is_object($value))
                                                <pre class="text-xs">{{ json_encode($value) }}</pre>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-gray-500">
                    No results found or query triggered no dataset.
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
