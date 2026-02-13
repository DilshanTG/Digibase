<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class DataNexus extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected string $view = 'filament.pages.data-nexus';

    protected static string|UnitEnum|null $navigationGroup = 'Data Engine';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Universal Studio';

    public ?string $activeTable = null;

    public string $viewMode = 'data'; // 'data' or 'definition'

    public Collection $tables;

    public function mount(): void
    {
        $this->tables = DynamicModel::all();

        $this->activeTable = request()->query('table');
        $this->viewMode = request()->query('mode', 'data');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function switchTable(string $tableName): void
    {
        $this->activeTable = $tableName;
        $this->dispatch('table-switched', tableName: $tableName);
    }

    public function switchMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function syncDatabase(): void
    {
        if (! $this->activeTable) {
            return;
        }

        $record = $this->getActiveModel();
        $tableName = $record->table_name;
        $fields = $record->fields;

        if (! \Illuminate\Support\Facades\Schema::hasTable($tableName)) {
            \Illuminate\Support\Facades\Schema::create($tableName, function (\Illuminate\Database\Schema\Blueprint $table) use ($fields, $record) {
                $table->id();
                foreach ($fields as $field) {
                    $type = $field->type === 'image' || $field->type === 'file' ? 'string' : $field->type;
                    $column = $table->{$type}($field->name);
                    if (! $field->is_required) {
                        $column->nullable();
                    }
                    if ($field->is_unique) {
                        $column->unique();
                    }
                }
                if ($record->has_timestamps) {
                    $table->timestamps();
                }
                if ($record->has_soft_deletes) {
                    $table->softDeletes();
                }
            });
        } else {
            \Illuminate\Support\Facades\Schema::table($tableName, function (\Illuminate\Database\Schema\Blueprint $table) use ($fields, $tableName) {
                foreach ($fields as $field) {
                    if (! \Illuminate\Support\Facades\Schema::hasColumn($tableName, $field->name)) {
                        $type = $field->type === 'image' || $field->type === 'file' ? 'string' : $field->type;
                        $column = $table->{$type}($field->name);
                        if (! $field->is_required) {
                            $column->nullable();
                        }
                        if ($field->is_unique) {
                            $column->unique();
                        }
                    }
                }
            });
        }

        Notification::make()
            ->title('Database Synced')
            ->success()
            ->send();

        $this->dispatch('db-synced');
    }

    public function getActiveModel(): ?DynamicModel
    {
        return $this->tables->firstWhere('table_name', $this->activeTable);
    }

    public function getSystemColorProperty(): string
    {
        return config('digibase.branding.primary_color') ?? '#3ecf8e';
    }
}
