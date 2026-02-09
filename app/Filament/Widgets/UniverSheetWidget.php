<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\DynamicModel;
use App\Models\DynamicRecord;
use App\Models\ApiKey;
use Illuminate\Support\Facades\Log;

class UniverSheetWidget extends Widget
{
    protected string $view = 'filament.widgets.univer-sheet';
    protected int|string|array $columnSpan = 'full';

    public ?string $tableId = null;

    public function mount()
    {
        // Try to get tableId from query string if not set
        if (!$this->tableId) {
            $this->tableId = request()->query('tableId') ?? request()->query('tableid');
        }
    }

    protected function getViewData(): array
    {
        if (!$this->tableId) {
            return ['hasData' => false];
        }

        $dynamicModel = DynamicModel::with('fields')->find($this->tableId);

        if (!$dynamicModel) {
            return ['hasData' => false];
        }

        // 1. Fetch Schema (Fields)
        $schema = $dynamicModel->fields->map(function ($field) {
            return [
                'name' => $field->name,
                'type' => $field->type,
                'label' => $field->label ?? $field->name,
                'id' => $field->id,
            ];
        })->toArray();

        // 2. Fetch Records (Data)
        try {
            $modelClass = new DynamicRecord();
            $modelClass->setDynamicTable($dynamicModel->table_name);
            
            // Fetch ID + all fields defined in schema
            $records = $modelClass->get()->toArray();
        } catch (\Exception $e) {
            Log::error("Univer Widget Data Error: " . $e->getMessage());
            $records = [];
        }

        // 3. API Context
        $apiKey = ApiKey::where('is_active', true)->first()?->key ?? 'DEMO_KEY';

        return [
            'hasData' => true,
            'tableId' => $this->tableId,
            'tableName' => $dynamicModel->table_name,
            'schema' => $schema,
            'tableData' => $records,
            'saveUrl' => url('/api/data/' . $dynamicModel->table_name),
            'csrfToken' => csrf_token(),
            'apiToken' => $apiKey,
        ];
    }
}
