<?php

namespace App\Livewire\DataNexus;

use App\Models\DynamicModel;
use App\Models\DynamicRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class Grid extends Component
{
    public string $currentTable = '';

    public ?int $modelId = null;

    public array $columns = [];

    public int $perPage = 100;

    public int $page = 1;

    public int $totalRows = 0;

    public function mount(string $table): void
    {
        $this->currentTable = $table;
        $this->loadSchema();
    }

    #[On('table-switched')]
    public function updateTable(string $tableName): void
    {
        $this->currentTable = $tableName;
        $this->page = 1;
        $this->loadSchema();
    }

    protected function loadSchema(): void
    {
        $model = DynamicModel::where('table_name', $this->currentTable)->first();
        if (! $model) {
            $this->columns = [];
            $this->modelId = null;

            return;
        }

        $this->modelId = $model->id;
        $this->columns = $model->fields->map(fn ($f) => [
            'name' => $f->name,
            'display' => $f->display_name ?? Str::headline($f->name),
            'type' => $f->type,
        ])->toArray();
    }

    public function getRowsProperty(): Collection
    {
        if (! $this->currentTable || ! Schema::hasTable($this->currentTable)) {
            return collect();
        }

        $query = DB::table($this->currentTable);
        $this->totalRows = $query->count();

        $rows = $query
            ->orderBy('id', 'asc')
            ->offset(($this->page - 1) * $this->perPage)
            ->limit($this->perPage)
            ->get();

        // Load Spatie media URLs for file/image columns
        $fileColumns = collect($this->columns)->whereIn('type', ['file', 'image']);
        if ($fileColumns->isNotEmpty() && $rows->isNotEmpty() && Schema::hasTable('media')) {
            $rowIds = $rows->pluck('id')->toArray();

            // DynamicRecord stores media using the table name as model_type, OR the class name
            // We'll check both to be safe, but based on tinkering it's the table name
            $media = DB::table('media')
                ->whereIn('model_type', [$this->currentTable, 'App\\Models\\DynamicRecord', 'mobile_phones']) // covering bases
                ->whereIn('model_id', $rowIds)
                ->select('model_id', 'collection_name', 'file_name', 'disk', 'id as media_id')
                ->get()
                ->groupBy('model_id');

            foreach ($rows as $row) {
                $rowMedia = $media->get($row->id, collect());
                $row->_media = [];
                foreach ($fileColumns as $col) {
                    // Try both 'images'/'files' AND the column name itself as collection
                    // Spatie often uses the column name as the collection name in simple setups
                    $matched = $rowMedia->filter(function ($m) use ($col) {
                        return in_array($m->collection_name, ['images', 'files', $col['name'], 'default']);
                    });

                    // Construct URL based on disk - digibase_storage maps to public
                    $row->_media[$col['name']] = $matched->map(function ($m) {
                        return "/storage/{$m->media_id}/{$m->file_name}";
                    })->values()->toArray();
                }
            }
        }

        return $rows;
    }

    public function updateCell(int $id, string $column, ?string $value): void
    {
        if (! $this->currentTable || ! Schema::hasTable($this->currentTable)) {
            return;
        }

        // Find the column type for proper casting
        $colType = collect($this->columns)->firstWhere('name', $column);
        $castValue = $value;

        if ($colType) {
            $castValue = match ($colType['type']) {
                'integer' => $value !== '' && $value !== null ? (int) $value : null,
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                default => $value,
            };
        }

        DB::table($this->currentTable)
            ->where('id', $id)
            ->update([
                $column => $castValue,
                ...Schema::hasColumn($this->currentTable, 'updated_at')
                    ? ['updated_at' => now()]
                    : [],
            ]);
    }

    public function insertRow(): void
    {
        if (! $this->currentTable || ! Schema::hasTable($this->currentTable)) {
            return;
        }

        $data = [];
        foreach ($this->columns as $col) {
            if (in_array($col['type'], ['file', 'image'])) {
                continue;
            }
            $data[$col['name']] = match ($col['type']) {
                'boolean' => false,
                'integer' => 0,
                default => '',
            };
        }

        if (Schema::hasColumn($this->currentTable, 'created_at')) {
            $data['created_at'] = now();
            $data['updated_at'] = now();
        }

        DB::table($this->currentTable)->insert($data);
    }

    public function deleteRow(int $id): void
    {
        if (! $this->currentTable || ! Schema::hasTable($this->currentTable)) {
            return;
        }

        if (Schema::hasColumn($this->currentTable, 'deleted_at')) {
            DB::table($this->currentTable)->where('id', $id)->update(['deleted_at' => now()]);
        } else {
            DB::table($this->currentTable)->where('id', $id)->delete();
        }
    }

    public function nextPage(): void
    {
        if ($this->page * $this->perPage < $this->totalRows) {
            $this->page++;
        }
    }

    public function prevPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function render()
    {
        return view('livewire.data-nexus.grid', [
            'rows' => $this->rows,
        ]);
    }
}
