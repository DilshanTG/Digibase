<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Filament\Support\ArrayRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;
use Illuminate\Pagination\LengthAwarePaginator;

class RecycleBin extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trash';
    protected static string|UnitEnum|null $navigationGroup = 'Monitoring & Logs';
    protected static ?string $title = 'Recycle Bin';
    protected string $view = 'filament.pages.recycle-bin';
    protected static ?int $navigationSort = 99;



    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->getRawRecords())
            ->columns([
                Tables\Columns\TextColumn::make('table_name')
                    ->label('Table')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('record_id')
                    ->label('Record ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Deleted At')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->actions([
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Restore Record')
                    ->modalDescription('Are you sure you want to restore this record?')
                    ->action(function (?array $record) {
                        if (!$record) return;
                        $updateData = ['deleted_at' => null];
                        
                        // Only try to update updated_at if the table supports it
                        if (isset($record['has_timestamps']) && $record['has_timestamps']) {
                            $updateData['updated_at'] = now();
                        }

                        DB::table($record['table_name'])
                            ->where('id', $record['record_id'])
                            ->update($updateData);

                        Notification::make()
                            ->title('Record Restored')
                            ->success()
                            ->send();
                    }),

                Action::make('force_delete')
                    ->label('Destroy')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Permanently Delete Record')
                    ->modalDescription('This action cannot be undone. Are you sure?')
                    ->action(function (?array $record) {
                        if (!$record) return;
                        DB::table($record['table_name'])
                            ->where('id', $record['record_id'])
                            ->delete();

                        Notification::make()
                            ->title('Record Permanently Deleted')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('restore_selected')
                        ->label('Restore Selected')
                        ->icon('heroicon-m-arrow-uturn-left')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Restore Selected Records')
                        ->modalDescription('Are you sure you want to restore the selected records?')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                $updateData = ['deleted_at' => null];
                                
                                if (isset($record['has_timestamps']) && $record['has_timestamps']) {
                                    $updateData['updated_at'] = now();
                                }

                                DB::table($record['table_name'])
                                    ->where('id', $record['record_id'])
                                    ->update($updateData);
                            }

                            Notification::make()
                                ->title('Records Restored')
                                ->success()
                                ->send();
                        }),

                    BulkAction::make('force_delete_selected')
                        ->label('Destroy Selected')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Permanently Delete Selected Records')
                        ->modalDescription('This action cannot be undone. Are you sure?')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                DB::table($record['table_name'])
                                    ->where('id', $record['record_id'])
                                    ->delete();
                            }

                            Notification::make()
                                ->title('Records Permanently Deleted')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No Deleted Records')
            ->emptyStateDescription('All deleted records will appear here.')
            ->emptyStateIcon('heroicon-o-trash')
            ->paginated(true);
    }

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model|array $record): string
    {
        if (is_array($record)) {
            return $record[ArrayRecord::getKeyName()] ?? $record['id'];
        }

        return (string) $record->getKey();
    }

    public function resolveTableRecord(?string $key): array | \Illuminate\Database\Eloquent\Model | null
    {
        if ($key === null) {
            return null;
        }

        return $this->getRawRecords()->firstWhere(ArrayRecord::getKeyName(), $key);
    }

    public function getTableRecords(): Collection|LengthAwarePaginator
    {
        if (isset($this->cachedTableRecords)) {
            return $this->cachedTableRecords;
        }

        $records = $this->getRawRecords();
        $perPage = $this->getTableRecordsPerPage();

        if ($perPage === 'all') {
            return $this->cachedTableRecords = $records;
        }

        $currentPage = LengthAwarePaginator::resolveCurrentPage('page');

        return $this->cachedTableRecords = new LengthAwarePaginator(
            $records->forPage($currentPage, (int) $perPage),
            $records->count(),
            (int) $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    public function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        return null;
    }

    public function getAllSelectableTableRecordsCount(): int
    {
        $records = $this->getTableRecords();

        return ($records instanceof LengthAwarePaginator) ? $records->total() : $records->count();
    }

    protected function getRawRecords(): Collection
    {
        $models = DynamicModel::where('has_soft_deletes', true)
            ->where('is_active', true)
            ->get();

        $deletedRecords = collect();

        foreach ($models as $model) {
            if (!DB::getSchemaBuilder()->hasTable($model->table_name)) {
                continue;
            }

            if (!DB::getSchemaBuilder()->hasColumn($model->table_name, 'deleted_at')) {
                continue;
            }

            $records = DB::table($model->table_name)
                ->whereNotNull('deleted_at')
                ->select('id as record_id', 'deleted_at')
                ->get()
                ->map(fn ($item) => [
                    ArrayRecord::getKeyName() => $model->table_name . '_' . $item->record_id,
                    'id' => $model->table_name . '_' . $item->record_id, // Keep id for backward compatibility/clarity
                    'record_id' => $item->record_id,
                    'deleted_at' => $item->deleted_at,
                    'table_name' => $model->table_name,
                    'display_name' => $model->display_name,
                    'has_timestamps' => (bool) $model->has_timestamps,
                ]);

            $deletedRecords = $deletedRecords->merge($records);
        }

        // Sort by deleted_at descending
        return $deletedRecords->sortByDesc('deleted_at')->values();
    }


}
