@php
    use App\Models\DynamicModel;
    use App\Models\DynamicRecord;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Str;
    
    // $dynamicModel and $tableName are passed directly from the Placeholder component
    if (!isset($dynamicModel) || !$dynamicModel) {
        return '<div class="text-center py-4 text-slate-400">No model data available</div>';
    }
    
    $tableName = $tableName ?? $dynamicModel->table_name ?? null;
    if (!$tableName) {
        return '<div class="text-center py-4 text-slate-400">No table specified</div>';
    }
    
    // Check if table exists and is accessible
    $tableExists = Schema::hasTable($tableName);
    $records = collect();
    $columns = [];
    
    if ($tableExists) {
        try {
            $query = (new DynamicRecord)->setDynamicTable($tableName)->newQuery();
            
            if ($dynamicModel->has_soft_deletes && Schema::hasColumn($tableName, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            
            $records = $query->limit(5)->get(); // Preview only 5 records
            
            // Get column definitions
            foreach ($dynamicModel->fields as $field) {
                $columns[] = [
                    'name' => $field->name,
                    'display_name' => $field->display_name ?? Str::headline($field->name),
                    'type' => $field->type,
                ];
            }
        } catch (\Exception $e) {
            $tableExists = false;
        }
    }
@endphp

<div class="data-nexus-live-preview" x-data="{ isOpen: @entangle('isPreviewOpen') }">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4 p-4 bg-slate-900/50 rounded-lg border border-emerald-500/20 backdrop-blur-xl">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-slate-100">Live Data Preview</h3>
                <p class="text-sm text-slate-400">Real-time view of {{ $dynamicModel->display_name }} data</p>
            </div>
        </div>
        
        <div class="flex items-center space-x-2 nexus-loading" x-data="{ loading: false }">
            <!-- Sync Status Badge -->
            @php
                $isOutOfSync = false;
                if ($tableExists && $dynamicModel->fields->isNotEmpty()) {
                    $dbColumns = Schema::getColumnListing($tableName);
                    $modelColumns = $dynamicModel->fields->pluck('name')->toArray();
                    $missingColumns = array_diff($modelColumns, $dbColumns);
                    $isOutOfSync = !empty($missingColumns);
                }
            @endphp
            
            @if($isOutOfSync)
                <div class="flex items-center space-x-2 px-3 py-1 bg-amber-500/20 border border-amber-500/30 rounded-full">
                    <div class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></div>
                    <span class="text-xs text-amber-400 font-medium">Out of Sync</span>
                </div>
            @else
                <div class="flex items-center space-x-2 px-3 py-1 bg-emerald-500/20 border border-emerald-500/30 rounded-full">
                    <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                    <span class="text-xs text-emerald-400 font-medium">Synced</span>
                </div>
            @endif
            
            <button 
                @click="isOpen = !isOpen; loading = true; setTimeout(() => loading = false, 1000)"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-all duration-200 flex items-center space-x-2 nexus-command-btn"
                :disabled="loading"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span>Refresh</span>
            </button>
        </div>
    </div>

    <!-- Content -->
    @if(!$tableExists)
        <div class="text-center py-8">
            <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p class="text-slate-400 font-medium">Table not found</p>
            <p class="text-sm text-slate-500 mt-1">Sync the database schema to create this table</p>
        </div>
    @elseif($records->isEmpty())
        <div class="text-center py-8">
            <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
            </div>
            <p class="text-slate-400 font-medium">No records yet</p>
            <p class="text-sm text-slate-500 mt-1">Add your first record to see data here</p>
        </div>
    @else
        <!-- Data Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">ID</th>
                        @foreach($columns as $column)
                            <th class="text-left py-3 px-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                {{ $column['display_name'] }}
                            </th>
                        @endforeach
                        <th class="text-left py-3 px-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($records as $record)
                        <tr class="hover:bg-slate-800/50 transition-colors duration-150">
                            <td class="py-3 px-4">
                                <span class="text-xs font-mono bg-slate-700/50 text-slate-300 px-2 py-1 rounded">
                                    #{{ $record->id }}
                                </span>
                            </td>
                            @foreach($columns as $column)
                                <td class="py-3 px-4">
                                    @php
                                        $value = $record->{$column['name']};
                                        $displayValue = '';
                                        
                                        if ($column['type'] === 'boolean') {
                                            $displayValue = $value ? 
                                                '<span class="text-emerald-400">✓</span>' : 
                                                '<span class="text-red-400">✗</span>';
                                        } elseif ($column['type'] === 'file' || $column['type'] === 'image') {
                                            if (is_string($value) && Str::startsWith($value, 'http')) {
                                                $displayValue = '<img src="' . $value . '" class="h-8 w-8 object-cover rounded" />';
                                            } else {
                                                $displayValue = '<span class="text-slate-500">📎 File</span>';
                                            }
                                        } elseif ($column['type'] === 'datetime' || $column['type'] === 'date') {
                                            $displayValue = $value ? \Carbon\Carbon::parse($value)->format('M j, Y') : '-';
                                        } else {
                                            $displayValue = Str::limit($value ?? '', 30);
                                        }
                                    @endphp
                                    <span class="text-sm text-slate-300">{!! $displayValue !!}</span>
                                </td>
                            @endforeach
                            <td class="py-3 px-4">
                                <span class="text-xs text-slate-500">
                                    {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->diffForHumans() : '-' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        @if($records->count() >= 5)
            <div class="mt-4 text-center">
                <a href="{{ route('filament.admin.pages.data-explorer', ['table' => $tableName]) }}" 
                   class="inline-flex items-center space-x-2 text-emerald-400 hover:text-emerald-300 transition-colors duration-200">
                    <span>View all records</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
        @endif
    @endif
</div>