<?php

namespace App\Filament\Resources\DynamicModelResource\Pages;

use App\Filament\Resources\DynamicModelResource;
use App\Models\DynamicField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDynamicModel extends CreateRecord
{
    protected static string $resource = DynamicModelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $tableName = $record->table_name;

        if (Schema::hasTable($tableName)) {
            Notification::make()
                ->warning()
                ->title('Table already exists')
                ->body("The database table \"{$tableName}\" already exists. Skipped creation.")
                ->send();

            return;
        }

        $fields = $record->fields;

        Schema::create($tableName, function (Blueprint $table) use ($record, $fields) {
            $table->id();

            foreach ($fields as $field) {
                $column = $this->addColumnToTable($table, $field);

                if ($column) {
                    if (! $field->is_required) {
                        $column->nullable();
                    }

                    if ($field->is_unique) {
                        $column->unique();
                    }

                    if ($field->default_value !== null && $field->default_value !== '') {
                        $column->default($field->default_value);
                    }

                    if ($field->is_indexed) {
                        $column->index();
                    }
                }
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
            ->body("Database table \"{$tableName}\" created with " . $fields->count() . " column(s).")
            ->send();
    }

    private function addColumnToTable(Blueprint $table, DynamicField $field)
    {
        $name = $field->name;

        return match ($field->getDatabaseType()) {
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
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
