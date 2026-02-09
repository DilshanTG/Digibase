<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use App\Models\DynamicRecord;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use BackedEnum;
use UnitEnum;

class DataExplorer extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';
    protected static string|UnitEnum|null $navigationGroup = 'Database';
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
        $columns[] = TextColumn::make('id')->sortable();

        if ($dynamicModel->fields->isNotEmpty()) {
            foreach ($dynamicModel->fields as $field) {
                // Use TextColumn for safe display (XSS protection)
                $columns[] = TextColumn::make($field->name)
                    ->label($field->display_name ?? Str::headline($field->name))
                    ->sortable()
                    ->searchable()
                    ->limit(50); // Truncate long text
            }
        }
        
        $columns[] = TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true);

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
            ]);
    }

    protected function getDynamicForm($dynamicModel): array
    {
        $fields = [];
        if ($dynamicModel->fields->isNotEmpty()) {
            foreach ($dynamicModel->fields as $field) {
                $component = match ($field->type) {
                    'boolean' => Toggle::make($field->name),
                    'date' => DatePicker::make($field->name),
                    'datetime' => DateTimePicker::make($field->name),
                    'file' => FileUpload::make($field->name),
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
