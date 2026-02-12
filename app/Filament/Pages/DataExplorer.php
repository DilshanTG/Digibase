<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use App\Models\DynamicRecord;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use BackedEnum;
use UnitEnum;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

class DataExplorer extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';
    protected static string|UnitEnum|null $navigationGroup = 'Data Engine';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Data Explorer';
    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.pages.data-explorer';

    public ?string $tableId = null;
    public bool $isSpreadsheet = false;

    public function mount(): void
    {
        if (!$this->tableId) {
            $this->tableId = request()->query('tableId') ?? request()->query('tableid');
            
            // Handle 'table' (name) parameter
            $tableNameParam = request()->query('table');
            if (!$this->tableId && $tableNameParam) {
                $model = DynamicModel::where('table_name', $tableNameParam)->first();
                if ($model) {
                    $this->tableId = $model->id;
                }
            }
        }
        $this->isSpreadsheet = (bool) request()->query('spreadsheet');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apiDocs')
                ->label('API Docs')
                ->icon('heroicon-o-book-open')
                ->color('info')
                ->url(fn () => route('filament.admin.pages.api-documentation', ['model' => $this->tableId]))
                ->openUrlInNewTab()
                ->visible(fn () => $this->tableId !== null),
            Action::make('toggleSpreadsheet')
                ->label($this->isSpreadsheet ? 'Standard View' : 'Spreadsheet View')
                ->icon($this->isSpreadsheet ? 'heroicon-o-table-cells' : 'heroicon-o-squares-2x2')
                ->color($this->isSpreadsheet ? 'gray' : 'primary')
                ->action(fn () => $this->isSpreadsheet = ! $this->isSpreadsheet)
                ->visible(fn () => $this->tableId !== null),
            Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->form([
                    FileUpload::make('file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->disk('local')
                        ->directory('temp-imports')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $dynamicModel = DynamicModel::find($this->tableId);
                    if (!$dynamicModel) return;

                    $path = Storage::disk('local')->path($data['file']);
                    
                    if (!file_exists($path)) {
                        Notification::make()->title('File not found')->danger()->send();
                        return;
                    }

                    $handle = fopen($path, 'r');
                    $header = fgetcsv($handle);
                    
                    if (!$header) {
                        Notification::make()->title('Empty or invalid CSV')->danger()->send();
                        return;
                    }
                    
                    $count = 0;
                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($header) !== count($row)) continue;
                        
                        $recordData = array_combine($header, $row);
                        
                        $record = new DynamicRecord();
                        $record->setTable($dynamicModel->table_name);
                        
                        foreach ($recordData as $key => $value) {
                             if ($value === '' || $value === null) $value = null;
                             $record->{$key} = $value;
                        }
                        
                        try {
                            $record->save();
                            $count++;
                        } catch (\Exception $e) {
                            // Continue on error
                        }
                    }
                    
                    fclose($handle);
                    Storage::disk('local')->delete($data['file']);
                    
                    Notification::make()
                        ->title("Imported {$count} records successfully")
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->tableId !== null),
            Action::make('downloadTemplate')
                ->label('Download Template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    $dynamicModel = DynamicModel::find($this->tableId);
                    if (!$dynamicModel) return;

                    $headers = $dynamicModel->fields->pluck('name')->toArray();
                    
                    if (empty($headers)) {
                        $headers = ['name', 'created_at']; 
                    }

                    return response()->streamDownload(function () use ($headers) {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, $headers);
                        fclose($handle);
                    }, $dynamicModel->table_name . '-template.csv');
                })
                ->visible(fn () => $this->tableId !== null),
            ExportAction::make()
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->exports([
                    \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                        ->fromTable()
                        ->withFilename($this->tableId . '-' . date('Y-m-d') . '.xlsx'),
                ])
                ->visible(fn () => $this->tableId !== null),
        ];
    }

    public function table(Table $table): Table
    {
        // 1. If no table is selected, show an empty state
        if (! $this->tableId) {
            return $table->query(DynamicModel::query()->where('id', 0))->heading('Select a table to view data');
        }

        // 2. Load the Dynamic Model definition
        $dynamicModel = DynamicModel::find($this->tableId);
        if (! $dynamicModel) {
            return $table->query(DynamicModel::query()->where('id', 0));
        }

        // 2.1 Check if physical table exists
        if (! Schema::hasTable($dynamicModel->table_name)) {
            return $table
                ->query(DynamicModel::query()->where('id', 0))
                ->emptyStateHeading("Database table '{$dynamicModel->table_name}' not found.")
                ->emptyStateDescription("Please go back to Table Builder and ensure the table is properly created.")
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

                // 🎯 Category B: Image Rendering & Boolean Polish
                if (in_array($field->type, ['file', 'image'])) {
                    $column = SpatieMediaLibraryImageColumn::make($field->name)
                        ->collection($field->type === 'image' ? 'images' : 'files')
                        ->conversion('thumb')
                        ->circular(false)
                        ->stacked()
                        ->limit(3);
                } elseif ($field->type === 'boolean') {
                    $column = IconColumn::make($field->name)
                        ->boolean()
                        ->trueIcon('heroicon-o-check-circle')
                        ->falseIcon('heroicon-o-x-circle')
                        ->trueColor('success')
                        ->falseColor('danger');
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
                return (new DynamicRecord())->setDynamicTable($dynamicModel->table_name)->newQuery();
            })
            ->columns($columns)
            ->heading($dynamicModel->display_name . " Data")
            ->headerActions([
                CreateAction::make()
                    ->schema($this->getDynamicForm($dynamicModel))
                    ->using(function (array $data) use ($dynamicModel) {
                        $record = new DynamicRecord();
                        $record->setDynamicTable($dynamicModel->table_name);
                        $record->fill($data);
                        $record->save();
                        return $record;
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
                        $record->delete();
                    }),
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
