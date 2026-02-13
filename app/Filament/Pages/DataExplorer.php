<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use App\Models\DynamicRecord;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use UnitEnum;

class DataExplorer extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static string|UnitEnum|null $navigationGroup = 'Data Engine';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Data Explorer';

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.pages.data-explorer';

    public ?string $tableName = null;

    public ?DynamicModel $dynamicModel = null;

    public function mount(): void
    {
        // 🛡️ Iron Dome: Full System Stabilization
        $table = request()->query('table');

        // 1. Validation: Check if table exists
        $model = DynamicModel::where('table_name', $table)->first();

        if (! $model) {
            // Safe Fallback: Redirect to the first available table
            $first = DynamicModel::first();
            if ($first) {
                $this->redirect(static::getUrl(['table' => $first->table_name]));

                return;
            } else {
                // Absolute Empty State
                Notification::make()
                    ->title('No tables found. Please create a Model first.')
                    ->warning()
                    ->send();

                return;
            }
        }

        $this->tableName = $table;
        $this->dynamicModel = $model;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apiDocs')
                ->label('API Docs')
                ->icon('heroicon-o-book-open')
                ->color('info')
                ->url(fn () => route('filament.admin.pages.api-documentation', ['model' => $this->dynamicModel?->id]))
                ->openUrlInNewTab()
                ->visible(fn () => $this->dynamicModel?->id !== null),

            Action::make('downloadTemplate')
                ->label('Download Template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    if (! $this->dynamicModel) {
                        return;
                    }

                    $headers = $this->dynamicModel->fields->pluck('name')->toArray();

                    if (empty($headers)) {
                        $headers = ['name', 'created_at'];
                    }

                    return response()->streamDownload(function () use ($headers) {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, $headers);
                        fclose($handle);
                    }, $this->dynamicModel->table_name.'-template.csv');
                })
                ->visible(fn () => $this->dynamicModel !== null),
            ExportAction::make()
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->exports([
                    \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                        ->fromTable()
                        ->withFilename($this->dynamicModel?->id.'-'.date('Y-m-d').'.xlsx'),
                ])
                ->visible(fn () => $this->dynamicModel?->id !== null),
        ];
    }

    public function table(Table $table): Table
    {
        // 🛡️ Guard Clause: Prevent white screen if state is lost
        if (! $this->tableName || ! $this->dynamicModel) {
            return $table->columns([])->emptyStateHeading('No table selected');
        }

        // 2. Use the loaded Dynamic Model definition
        $dynamicModel = $this->dynamicModel;
        $tableName = $dynamicModel->table_name;

        // 2.1 Check if physical table exists
        if (! Schema::hasTable($dynamicModel->table_name)) {
            return $table
                ->query(DynamicModel::query()->where('id', 0))
                ->emptyStateHeading("Database table '{$dynamicModel->table_name}' not found.")
                ->emptyStateDescription('Please go back to Table Builder and ensure the table is properly created.')
                ->columns([
                    TextColumn::make('status')
                        ->getStateUsing(fn () => 'Missing Table')
                        ->badge()
                        ->color('danger'),
                ]);
        }

        // 3. Build Dynamic Columns
        $columns = [];
        $columns[] = TextColumn::make('id')
            ->sortable()
            ->badge()
            ->color('gray')
            ->copyable()
            ->alignEnd();

        if ($dynamicModel->fields->isNotEmpty()) {
            $index = 0;
            foreach ($dynamicModel->fields as $field) {
                $index++;
                $column = null;

                // 🎯 SMART COLUMN RENDERING
                if (in_array($field->type, ['file', 'image']) || in_array($field->name, ['image', 'photo', 'avatar', 'logo', 'thumbnail'])) {
                    $column = SpatieMediaLibraryImageColumn::make($field->name)
                        ->collection($field->type === 'image' ? 'images' : 'files')
                        ->conversion('thumb')
                        ->circular($field->name === 'avatar')
                        ->stacked()
                        ->limit(3);
                } elseif ($field->type === 'boolean') {
                    $column = IconColumn::make($field->name)
                        ->boolean()
                        ->trueIcon('heroicon-o-check-circle')
                        ->falseIcon('heroicon-o-x-circle')
                        ->trueColor('success')
                        ->falseColor('danger');
                } elseif ($field->name === 'color' || $field->name === 'hex') {
                    $column = \Filament\Tables\Columns\ColorColumn::make($field->name)
                        ->label($field->display_name ?? Str::headline($field->name));
                } elseif ($field->type === 'text' || $field->type === 'long_text') {
                    $column = TextColumn::make($field->name)
                        ->limit(50)
                        ->tooltip(fn ($state) => $state)
                        ->copyable();
                } else {
                    $column = TextColumn::make($field->name)
                        ->limit(50)
                        ->tooltip(fn ($state) => $state)
                        ->copyable()
                        ->html()
                        ->formatStateUsing(function ($state) {
                            if (is_string($state) && Str::startsWith($state, 'http') && preg_match('/\.(jpg|jpeg|png|webp|gif|svg)$/i', $state)) {
                                return '<img src="'.$state.'" class="h-10 w-10 object-cover rounded shadow-sm border border-gray-200 dark:border-gray-700 hover:scale-150 transition-transform cursor-zoom-in overflow-hidden" />';
                            }

                            return $state;
                        });

                    if (in_array($field->type, ['integer', 'decimal', 'float', 'number'])) {
                        $column->alignEnd();
                    }
                }

                $columns[] = $column
                    ->label($field->display_name ?? Str::headline($field->name))
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: $index > 5);
            }
        }

        $columns[] = TextColumn::make('created_at')
            ->label('Created')
            ->since()
            ->sortable()
            ->toggleable()
            ->color('gray');

        $columns[] = TextColumn::make('updated_at')
            ->label('Updated')
            ->since()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true)
            ->color('gray');

        // 4. Configure the Table
        return $table
            ->query(function () use ($dynamicModel) {
                $query = (new DynamicRecord)->setDynamicTable($dynamicModel->table_name)->newQuery();

                if ($dynamicModel->has_soft_deletes && Schema::hasColumn($dynamicModel->table_name, 'deleted_at')) {
                    $query->whereNull('deleted_at');
                }

                return $query;
            })
            ->columns($columns)
            ->heading($dynamicModel->display_name.' Data')
            ->headerActions([
                CreateAction::make()
                    ->schema($this->getDynamicForm($dynamicModel))
                    ->using(function (array $data) use ($dynamicModel) {
                        $record = new DynamicRecord;
                        $record->setDynamicTable($dynamicModel->table_name);
                        $record->fill($data);
                        $record->save();

                        return $record;
                    }),

                Action::make('import_csv')
                    ->label('Import CSV')
                    ->icon('heroicon-m-arrow-up-tray')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\FileUpload::make('attachment')
                            ->label('Upload CSV File')
                            ->disk('local')
                            ->directory('csv-imports')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv'])
                            ->required()
                            ->helperText('Upload a CSV file with column headers matching your table fields.'),
                    ])
                    ->action(function (array $data) use ($dynamicModel) {
                        $tableName = $dynamicModel->table_name;

                        // 1. Open File
                        $path = storage_path('app/'.$data['attachment']);

                        if (! file_exists($path)) {
                            Notification::make()
                                ->title('Error: File not found')
                                ->danger()
                                ->send();

                            return;
                        }

                        $handle = fopen($path, 'r');

                        if (! $handle) {
                            Notification::make()
                                ->title('Error opening file')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            // 2. Read Header
                            $header = fgetcsv($handle, 1000, ',');

                            if (! $header) {
                                throw new \Exception('CSV file is empty or invalid');
                            }

                            // 3. Process Rows
                            $batch = [];
                            $now = now();
                            $rowCount = 0;
                            $errorRows = [];

                            // Get valid columns to prevent SQL errors
                            $allowedColumns = Schema::getColumnListing($tableName);

                            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                                $rowCount++;

                                try {
                                    // 🛡️ Fault-Tolerant Row Matching
                                    // Normalize row length to match header (pad short rows, trim long rows)
                                    $headerCount = count($header);
                                    $rowCount_actual = count($row);

                                    if ($rowCount_actual < $headerCount) {
                                        // Missing columns: Pad with empty strings
                                        $row = array_pad($row, $headerCount, '');
                                    } elseif ($rowCount_actual > $headerCount) {
                                        // Too many columns: Truncate excess
                                        $row = array_slice($row, 0, $headerCount);
                                    }

                                    // Safe array_combine with matching lengths
                                    $record = array_combine($header, $row);

                                    // Filter only allowed columns
                                    $cleanRecord = [];
                                    foreach ($record as $key => $value) {
                                        if (in_array($key, $allowedColumns) && $key !== 'id') {
                                            $cleanRecord[$key] = $value;
                                        }
                                    }

                                    // Add timestamps if enabled
                                    if ($dynamicModel->has_timestamps) {
                                        $cleanRecord['created_at'] = $now;
                                        $cleanRecord['updated_at'] = $now;
                                    }

                                    $batch[] = $cleanRecord;
                                } catch (\ValueError|\Exception $e) {
                                    // Skip malformed row silently
                                    $errorRows[] = $rowCount;

                                    continue;
                                }

                                // Chunk Insert (Prevent Memory Overload)
                                if (count($batch) >= 200) {
                                    DB::table($tableName)->insert($batch);
                                    $batch = [];
                                }
                            }

                            // Insert Remaining
                            if (! empty($batch)) {
                                DB::table($tableName)->insert($batch);
                            }

                            fclose($handle);
                            @unlink($path); // Cleanup

                            $message = count($errorRows) > 0
                                ? "Imported {$rowCount} records with ".count($errorRows).' skipped rows'
                                : "Successfully imported {$rowCount} records";

                            Notification::make()
                                ->title('Import Complete')
                                ->body($message)
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            fclose($handle);
                            @unlink($path);

                            Notification::make()
                                ->title('Import Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('info')
                    ->action(function () use ($tableName, $dynamicModel) {
                        return response()->streamDownload(function () use ($tableName, $dynamicModel) {
                            $handle = fopen('php://output', 'w');

                            // Fetch records in chunks to prevent memory crash
                            $query = DB::table($tableName);

                            // Exclude soft-deleted records if soft deletes enabled
                            if ($dynamicModel->has_soft_deletes && Schema::hasColumn($tableName, 'deleted_at')) {
                                $query->whereNull('deleted_at');
                            }

                            $query->orderBy('id');

                            // Get columns
                            $columns = Schema::getColumnListing($tableName);
                            fputcsv($handle, $columns); // Header row

                            $query->chunk(500, function ($rows) use ($handle) {
                                foreach ($rows as $row) {
                                    fputcsv($handle, (array) $row);
                                }
                            });

                            fclose($handle);
                        }, $tableName.'_export_'.now()->format('Y-m-d_H-i').'.csv');
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->schema($this->getDynamicForm($dynamicModel))
                    ->using(function ($record, array $data) use ($dynamicModel) {
                        $record->setTable($dynamicModel->table_name);
                        $record->fill($data);
                        $record->save();

                        return $record;
                    }),
                DeleteAction::make()
                    ->using(function ($record) use ($dynamicModel) {
                        $record->setTable($dynamicModel->table_name);

                        if ($dynamicModel->has_soft_deletes && Schema::hasColumn($dynamicModel->table_name, 'deleted_at')) {
                            DB::table($dynamicModel->table_name)
                                ->where('id', $record->id)
                                ->update(['deleted_at' => now()]);

                            Notification::make()
                                ->title('Record moved to Recycle Bin')
                                ->success()
                                ->send();

                            return;
                        }

                        $record->delete();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(function (\Illuminate\Support\Collection $records) use ($dynamicModel) {
                            foreach ($records as $record) {
                                $record->setTable($dynamicModel->table_name);

                                if ($dynamicModel->has_soft_deletes && Schema::hasColumn($dynamicModel->table_name, 'deleted_at')) {
                                    DB::table($dynamicModel->table_name)
                                        ->where('id', $record->id)
                                        ->update(['deleted_at' => now()]);
                                } else {
                                    $record->delete();
                                }
                            }

                            Notification::make()
                                ->title($dynamicModel->has_soft_deletes ? 'Selected records moved to Recycle Bin' : 'Selected records deleted')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->striped();
    }

    /**
     * Generate dynamic form fields based on DynamicModel schema.
     * Now uses Spatie Media Library for file uploads.
     */
    protected function getDynamicForm($dynamicModel): array
    {
        $fields = [];
        if ($dynamicModel->fields->isNotEmpty()) {
            foreach ($dynamicModel->fields as $field) {
                $component = match ($field->type) {
                    'boolean' => Toggle::make($field->name),
                    'date' => DatePicker::make($field->name),
                    'datetime' => DateTimePicker::make($field->name),

                    // 🎯 UPGRADED: Use Spatie Media Library for files
                    'file' => SpatieMediaLibraryFileUpload::make($field->name)
                        ->collection('files')
                        ->multiple()
                        ->maxFiles(5)
                        ->maxSize(10240) // 10MB
                        ->downloadable()
                        ->openable()
                        ->previewable()
                        ->reorderable()
                        ->disk('digibase_storage'),

                    // 🎯 UPGRADED: Use Spatie Media Library for images with optimization
                    'image' => SpatieMediaLibraryFileUpload::make($field->name)
                        ->collection('images')
                        ->image()
                        ->imageEditor()
                        ->imageEditorAspectRatios([
                            null,
                            '16:9',
                            '4:3',
                            '1:1',
                        ])
                        ->multiple()
                        ->maxFiles(10)
                        ->maxSize(5120) // 5MB
                        ->downloadable()
                        ->openable()
                        ->previewable()
                        ->reorderable()
                        ->disk('digibase_storage')
                        ->conversion('preview'),

                    default => TextInput::make($field->name),
                };

                $fields[] = $component
                    ->label($field->display_name ?? Str::headline($field->name))
                    ->required($field->is_required);
            }
        }

        return $fields;
    }
}
