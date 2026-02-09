<?php

namespace App\Filament\Widgets;

use App\Models\DynamicModel;
use App\Models\ApiKey;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class UniverSheetWidget extends Widget
{
    protected string $view = 'filament.widgets.univer-sheet';

    public ?string $tableName = null;
    
    protected int | string | array $columnSpan = 'full';

    /**
     * Get data for the view
     */
    protected function getViewData(): array
    {
        // Resolve tableName from property, then query 'table', then query 'tableId'
        if (!$this->tableName) {
            $this->tableName = Request::query('table');
            
            if (!$this->tableName && $tableId = Request::query('tableId')) {
                $this->tableName = DynamicModel::find($tableId)?->table_name;
            }
        }

        if (!$this->tableName) {
            return [
                'tableName' => null,
                'schema' => [],
                'records' => [],
                'apiToken' => '',
            ];
        }

        $model = DynamicModel::where('table_name', $this->tableName)->with('fields')->first();
        
        if (!$model) {
            return [
                'tableName' => $this->tableName,
                'schema' => [],
                'records' => [],
                'apiToken' => '',
            ];
        }

        // Fetch records
        $records = DB::table($this->tableName)
            ->whereNull('deleted_at') // Handle soft deletes manually as per index logic
            ->limit(500) // Safety limit for spreadsheets
            ->get();

        // Get or Create an API Key for the current user for auto-save
        // In a real app, you might use a dedicated temporary session token.
        // For now, we'll try to find an active API Key or just use the session if the API supports it.
        $apiKey = ApiKey::where('is_active', true)->first()?->key ?? 'DEMO_KEY';

        return [
            'tableName' => $this->tableName,
            'schema' => $model->fields->toArray(),
            'records' => $records->toArray(),
            'apiToken' => $apiKey,
        ];
    }
}
