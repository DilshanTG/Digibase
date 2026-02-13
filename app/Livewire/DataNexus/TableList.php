<?php

namespace App\Livewire\DataNexus;

use App\Models\DynamicModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class TableList extends Component
{
    use WithPagination;

    public $search = '';

    public function render()
    {
        $query = DynamicModel::query();

        if ($this->search) {
            $query->where('table_name', 'like', '%'.$this->search.'%')
                ->orWhere('description', 'like', '%'.$this->search.'%');
        }

        $tables = $query->orderBy('updated_at', 'desc')->paginate(10);

        // Append metadata
        $tables->getCollection()->transform(function ($table) {
            if (Schema::hasTable($table->table_name)) {
                $table->rows_count = DB::table($table->table_name)->count();
                // Estimate size (very rough for SQLite/MySQL without heavy queries)
                // For now, placeholder or specific driver logic
                $table->size_estimated = 'Unknown';
            } else {
                $table->rows_count = 0;
                $table->size_estimated = '0 kB';
            }

            return $table;
        });

        return view('livewire.data-nexus.table-list', [
            'tables' => $tables,
        ]);
    }

    public function getSystemColorProperty()
    {
        return config('digibase.branding.primary_color') ?? '#3ecf8e';
    }

    public function deleteTable($id)
    {
        $model = DynamicModel::find($id);
        if ($model) {
            Schema::dropIfExists($model->table_name);
            $model->delete();
        }
    }
}
