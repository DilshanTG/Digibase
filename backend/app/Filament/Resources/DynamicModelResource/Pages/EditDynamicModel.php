<?php

namespace App\Filament\Resources\DynamicModelResource\Pages;

use App\Filament\Resources\DynamicModelResource;
use App\Models\DynamicField;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EditDynamicModel extends EditRecord
{
    protected static string $resource = DynamicModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    $tableName = $this->record->table_name;
                    if (Schema::hasTable($tableName)) {
                        Schema::drop($tableName);
                        Notification::make()
                            ->success()
                            ->title('Table dropped')
                            ->body("Database table \"{$tableName}\" has been removed.")
                            ->send();
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $tableName = $record->table_name;
        $fields = $record->fields()->get();

        if (! Schema::hasTable($tableName)) {
            // Table doesn't exist yet — create it fresh
            Schema::create($tableName, function (Blueprint $table) use ($record, $fields) {
                $table->id();
                foreach ($fields as $field) {
                    $this->addColumn($table, $field);
                }
                if ($record->has_timestamps) {
                    $table->timestamps();
                }
                if ($record->has_soft_deletes) {
                    $table->softDeletes();
                }
            });

            Notification::make()
                ->success()
                ->title('Table created')
                ->body("Database table \"{$tableName}\" was missing and has been created.")
                ->send();

            return;
        }

        // Table exists — add any missing columns
        $existingColumns = Schema::getColumnListing($tableName);
        $added = 0;

        Schema::table($tableName, function (Blueprint $table) use ($fields, $existingColumns, &$added) {
            foreach ($fields as $field) {
                if (! in_array($field->name, $existingColumns)) {
                    $col = $this->addColumn($table, $field);
                    if ($col) {
                        $col->nullable(); // new columns on existing table should be nullable
                    }
                    $added++;
                }
            }
        });

        if ($added > 0) {
            Notification::make()
                ->success()
                ->title('Table updated')
                ->body("Added {$added} new column(s) to \"{$tableName}\".")
                ->send();
        }
    }

    private function addColumn(Blueprint $table, DynamicField $field)
    {
        $name = $field->name;

        $column = match ($field->getDatabaseType()) {
            'string' => $table->string($name),
            'text' => $table->text($name),
            'bigInteger' => $table->bigInteger($name),
            'decimal' => $table->decimal($name, 16, 4),
            'boolean' => $table->boolean($name)->default(false),
            'date' => $table->date($name),
            'dateTime' => $table->dateTime($name),
            'time' => $table->time($name),
            'json' => $table->json($name),
            'uuid' => $table->uuid($name),
            default => $table->string($name),
        };

        if (! $field->is_required) {
            $column->nullable();
        }

        if ($field->is_unique) {
            $column->unique();
        }

        if ($field->default_value !== null && $field->default_value !== '') {
            $column->default($field->default_value);
        }

        return $column;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
