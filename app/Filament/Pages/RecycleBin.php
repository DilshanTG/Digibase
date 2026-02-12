<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
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

    protected function paginateTableQuery(\Illuminate\Database\Eloquent\Builder $query): Paginator
    {
        return $query->paginate(($this->getTableRecordsPerPage() === 'all') ? $query->count() : $this->getTableRecordsPerPage());
    }

    public function table(Table $table): Table
    {
        return $table
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
                    ->action(function ($record) {
                        DB::table($record->table_name)
                            ->where('id', $record->record_id)
                            ->update(['deleted_at' => null, 'updated_at' => now()]);

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
                    ->action(function ($record) {
                        DB::table($record->table_name)
                            ->where('id', $record->record_id)
                            ->delete();

                        Notification::make()
                            ->title('Record Permanently Deleted')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('restore_selected')
                    ->label('Restore Selected')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Restore Selected Records')
                    ->modalDescription('Are you sure you want to restore the selected records?')
                    ->action(function (Collection $records) {
                        foreach ($records as $record) {
                            DB::table($record->table_name)
                                ->where('id', $record->record_id)
                                ->update(['deleted_at' => null, 'updated_at' => now()]);
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
                            DB::table($record->table_name)
                                ->where('id', $record->record_id)
                                ->delete();
                        }

                        Notification::make()
                            ->title('Records Permanently Deleted')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No Deleted Records')
            ->emptyStateDescription('All deleted records will appear here.')
            ->emptyStateIcon('heroicon-o-trash')
            ->paginated(true);
    }

    public function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        // Return a dummy query - actual records are provided by getTableRecords()
        return DynamicModel::query()->whereRaw('1 = 0');
    }

    public function getTableRecords(): Collection|LengthAwarePaginator
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
                ->select(
                    'id as record_id',
                    'deleted_at',
                    DB::raw("'{$model->table_name}' as table_name"),
                    DB::raw("'{$model->display_name}' as display_name")
                )
                ->get();

            $deletedRecords = $deletedRecords->merge($records);
        }

        // Sort by deleted_at descending
        $deletedRecords = $deletedRecords->sortByDesc('deleted_at')->values();

        // Paginate manually
        $perPage = $this->getTableRecordsPerPage();
        if ($perPage === 'all') {
            $perPage = $deletedRecords->count();
        }

        $currentPage = LengthAwarePaginator::resolveCurrentPage('page');

        return new LengthAwarePaginator(
            $deletedRecords->forPage($currentPage, $perPage),
            $deletedRecords->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url()]
        );
    }
}
