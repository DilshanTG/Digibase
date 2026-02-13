<?php

namespace App\Filament\Resources\DynamicModelResource\Pages;

use App\Filament\Resources\DynamicModelResource;
use App\Models\DynamicModel;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ListDynamicModels extends ListRecords
{
    protected static string $resource = DynamicModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('sync_all_db')
                ->label('Sync All DB')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Sync All Database Tables')
                ->modalDescription('This will create or update all database tables to match their model definitions. Continue?')
                ->action(function () {
                    $models = DynamicModel::all();
                    $created = 0;
                    $updated = 0;
                    $errors = [];

                    foreach ($models as $model) {
                        try {
                            if (! Schema::hasTable($model->table_name)) {
                                // Create new table
                                Schema::create($model->table_name, function (Blueprint $table) use ($model) {
                                    $table->id();
                                    foreach ($model->fields as $field) {
                                        $type = $field->type === 'image' || $field->type === 'file' ? 'string' : $field->type;
                                        $column = $table->{$type}($field->name);
                                        if (! $field->is_required) {
                                            $column->nullable();
                                        }
                                        if ($field->is_unique) {
                                            $column->unique();
                                        }
                                    }
                                    if ($model->has_timestamps) {
                                        $table->timestamps();
                                    }
                                    if ($model->has_soft_deletes) {
                                        $table->softDeletes();
                                    }
                                });
                                $created++;
                            } else {
                                // Update existing table - add missing columns
                                $columnsAdded = 0;
                                Schema::table($model->table_name, function (Blueprint $table) use ($model, &$columnsAdded) {
                                    foreach ($model->fields as $field) {
                                        if (! Schema::hasColumn($model->table_name, $field->name)) {
                                            $type = $field->type === 'image' || $field->type === 'file' ? 'string' : $field->type;
                                            $column = $table->{$type}($field->name);
                                            if (! $field->is_required) {
                                                $column->nullable();
                                            }
                                            $columnsAdded++;
                                        }
                                    }

                                    // Add timestamps if enabled and don't exist
                                    if ($model->has_timestamps) {
                                        if (! Schema::hasColumn($model->table_name, 'created_at')) {
                                            $table->timestamp('created_at')->nullable();
                                            $columnsAdded++;
                                        }
                                        if (! Schema::hasColumn($model->table_name, 'updated_at')) {
                                            $table->timestamp('updated_at')->nullable();
                                            $columnsAdded++;
                                        }
                                    }

                                    // Add soft deletes if enabled and doesn't exist
                                    if ($model->has_soft_deletes && ! Schema::hasColumn($model->table_name, 'deleted_at')) {
                                        $table->softDeletes();
                                        $columnsAdded++;
                                    }
                                });
                                if ($columnsAdded > 0) {
                                    $updated++;
                                }
                            }
                        } catch (\Exception $e) {
                            $errors[] = "{$model->table_name}: {$e->getMessage()}";
                        }
                    }

                    $message = [];
                    if ($created > 0) {
                        $message[] = "Created {$created} new table(s)";
                    }
                    if ($updated > 0) {
                        $message[] = "Updated {$updated} existing table(s)";
                    }
                    if (empty($message)) {
                        $message[] = 'All tables are up to date';
                    }

                    Notification::make()
                        ->success()
                        ->title('Database Sync Complete')
                        ->body(implode(', ', $message))
                        ->send();
                }),
        ];
    }
}
