<?php

namespace App\Filament\Resources\DynamicModelResource\Pages;

use App\Filament\Resources\DynamicModelResource;
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
                ->before(function ($record) {
                    if (Schema::hasTable($record->table_name)) {
                        Schema::dropIfExists($record->table_name);
                    }
                })
                ->after(function () {
                    Notification::make()
                        ->success()
                        ->title('Table Deleted Successfully')
                        ->body('The table and all its data have been permanently removed.')
                        ->send();
                }),
        ];
    }

    protected function afterSave(): void
    {
        $model = $this->record;
        $tableName = $model->table_name;

        if (Schema::hasTable($tableName)) {
            $currentColumns = Schema::getColumnListing($tableName);
            $added = 0;

            Schema::table($tableName, function (Blueprint $table) use ($model, $currentColumns, &$added) {
                foreach ($model->fields as $field) {
                    if (! in_array($field->name, $currentColumns)) {
                        $column = match ($field->type) {
                            'string', 'file' => $table->string($field->name),
                            'text' => $table->text($field->name),
                            'integer' => $table->integer($field->name),
                            'boolean' => $table->boolean($field->name),
                            'date' => $table->date($field->name),
                            'datetime' => $table->dateTime($field->name),
                            default => $table->string($field->name),
                        };

                        // New columns on existing table should be nullable
                        $column->nullable();
                        $added++;
                    }
                }
            });

            if ($added > 0) {
                Notification::make()
                    ->success()
                    ->title('Schema Updated')
                    ->body("Added {$added} new column(s) to the database.")
                    ->send();
            }
        }
    }
}
