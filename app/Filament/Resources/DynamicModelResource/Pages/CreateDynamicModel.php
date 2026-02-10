<?php

namespace App\Filament\Resources\DynamicModelResource\Pages;

use App\Filament\Resources\DynamicModelResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Filament\Notifications\Notification;

class CreateDynamicModel extends CreateRecord
{
    protected static string $resource = DynamicModelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        // Ensure table_name is set
        if (empty($data['table_name'])) {
            $data['table_name'] = $data['name'];
        }

        // Ensure display_name is set
        if (empty($data['display_name'])) {
            $data['display_name'] = \Illuminate\Support\Str::headline($data['name']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $model = $this->record;
        $tableName = $model->table_name;

        if (Schema::hasTable($tableName)) {
            Notification::make()
                ->warning()
                ->title('Table Exists')
                ->body("The table '{$tableName}' already exists. Only metadata was saved.")
                ->send();
            return;
        }

        try {
            Schema::create($tableName, function (Blueprint $table) use ($model) {
                $table->id();

                foreach ($model->fields as $field) {
                    $column = match ($field->type) {
                        'string' => $table->string($field->name),
                        'file', 'image' => $table->string($field->name)->nullable(),
                        'text' => $table->text($field->name),
                        'integer' => $table->integer($field->name),
                        'boolean' => $table->boolean($field->name),
                        'date' => $table->date($field->name),
                        'datetime' => $table->dateTime($field->name),
                        default => $table->string($field->name),
                    };

                    if (! $field->is_required) {
                        $column->nullable();
                    }
                    if ($field->is_unique) {
                        $column->unique();
                    }
                }

                $table->timestamps();
            });

            Notification::make()
                ->success()
                ->title('Table Created Successfully')
                ->body("Database table '{$tableName}' is now ready!")
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error Creating Table')
                ->body($e->getMessage())
                ->send();
        }
    }
}
