<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DynamicModelResource\Pages;
use App\Filament\Resources\DynamicModelResource\RelationManagers;
use App\Models\DynamicModel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\Str;
use UnitEnum;

class DynamicModelResource extends Resource
{
    protected static ?string $model = DynamicModel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Table Builder';

    protected static ?string $modelLabel = 'Database Table';

    protected static string|UnitEnum|null $navigationGroup = 'Data Engine';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('is_active', true)->count();
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('API Information')
                    ->icon('heroicon-o-command-line')
                    ->schema([
                        Forms\Components\Placeholder::make('api_endpoint')
                            ->label('Public Endpoint')
                            ->content(fn ($record) => $record ? url("/api/v1/data/{$record->table_name}") : 'Available after creation')
                            ->helperText('Use this URL to fetch data via GET requests.'),
                    ])->visible(fn ($record) => $record !== null),
                Tabs::make('Table Configuration')
                    ->tabs([
                        Tabs\Tab::make('Definition')
                            ->icon('heroicon-o-table-cells')
                            ->schema([
                                Section::make('Model Details')
                                    ->icon('heroicon-o-cube')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Model Name')
                                            ->autofocus()

                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g., Customer Orders')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                                if ($state) {
                                                    $set('display_name', $state);
                                                    $set('table_name', Str::snake(Str::plural($state)));
                                                }
                                            }),

                                        Forms\Components\TextInput::make('table_name')
                                            ->label('Table Name (SQL)')
                                            ->required()
                                            ->unique('dynamic_models', 'table_name', ignoreRecord: true)
                                            ->maxLength(255)
                                            ->placeholder('e.g. phone_books')
                                            ->helperText('Lower case with underscores (snake_case)'),

                                        Forms\Components\TextInput::make('display_name')
                                            ->label('Display Name')

                                            ->placeholder('e.g. Phone Books')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('icon')
                                            ->label('Navigation Icon')

                                            ->placeholder('e.g., heroicon-o-shopping-cart')
                                            ->helperText('Pick an icon from heroicons.com (e.g., heroicon-o-user)')
                                            ->suffixIcon('heroicon-o-link')
                                            ->suffixAction(
                                                Action::make('openHeroicons')
                                                    ->icon('heroicon-o-arrow-top-right-on-square')
                                                    ->url('https://heroicons.com', true)
                                            ),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Description')

                                            ->placeholder('Describe what this table stores...')
                                            ->columnSpanFull(),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('Configuration')
                            ->icon('heroicon-o-cog')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->helperText('Enable or disable this model globally')
                                    ->default(true),
                                Forms\Components\Toggle::make('generate_api')
                                    ->label('Generate API')
                                    ->helperText('Create REST API endpoints for this table')
                                    ->default(true),
                                Forms\Components\Toggle::make('has_timestamps')
                                    ->label('Timestamps')
                                    ->helperText('Add created_at and updated_at columns')
                                    ->default(true),
                                Forms\Components\Toggle::make('has_soft_deletes')
                                    ->label('Enable Soft Deletes')
                                    ->helperText('Mark records as deleted instead of permanent removal')
                                    ->default(true),
                            ])->columns(2),

                        Tabs\Tab::make('Columns')
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                Section::make('Fields')
                                    ->icon('heroicon-o-list-bullet')
                                    ->schema([
                                        Forms\Components\Repeater::make('fields')
                                            ->relationship('fields')
                                            ->schema([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Column Name')
                                                    ->required()
                                                    ->regex('/^[a-z0-9_]+$/') // 🛡️ Only snake_case
                                                    ->notIn(['id', 'created_at', 'updated_at', 'deleted_at', 'order', 'group', 'select', 'table', 'from', 'where', 'limit', 'public', 'user', 'key', 'index', 'primary', 'foreign', 'references', 'constraint', 'check', 'default', 'values', 'insert', 'update', 'delete', 'drop', 'alter', 'create', 'grant', 'revoke'])
                                                    ->validationMessages([
                                                        'not_in' => 'This field name is reserved by the system database. Please use a different name (e.g., instead of "order", use "sort_order").',
                                                        'regex' => 'Use only lowercase letters, numbers, and underscores (snake_case). Example: "user_name" or "total_price"',
                                                    ])
                                                    ->placeholder('user_name, total_price, etc.')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('display_name', $state ? Str::headline($state) : '')
                                                    ),

                                                Forms\Components\Hidden::make('display_name'),

                                                Forms\Components\Select::make('type')
                                                    ->label('Type')
                                                    ->options([
                                                        'string' => 'String - Text up to 255 chars',
                                                        'text' => 'Text - Large text blocks',
                                                        'integer' => 'Integer - Whole numbers',
                                                        'boolean' => 'Boolean - Yes/No values',
                                                        'date' => 'Date - Calendar dates',
                                                        'datetime' => 'DateTime - Date & time',
                                                        'json' => 'JSON - Complex data',
                                                        'file' => 'File - Uploads & images',
                                                    ])
                                                    ->required()
                                                    ->disabled(fn ($get) => $get('id') !== null)
                                                    ->helperText(fn ($get) => $get('id') !== null
                                                        ? 'Type cannot be changed after creation to prevent data loss. Delete and recreate if necessary.'
                                                        : 'Choose the appropriate data type for this field'),

                                                Forms\Components\Checkbox::make('is_required')
                                                    ->label('Required')
                                                    ->default(false),

                                                Forms\Components\Checkbox::make('is_unique')
                                                    ->label('Unique')
                                                    ->default(false),

                                                Forms\Components\Checkbox::make('is_hidden')
                                                    ->label('Hidden (API)')
                                                    ->helperText('Hide from API responses')
                                                    ->default(false),

                                                Forms\Components\TagsInput::make('validation_rules')
                                                    ->label('Custom Rules')
                                                    ->placeholder('Add rule (e.g. email, min:8)')
                                                    ->helperText('Laravel validation rules. Press Enter to add.')
                                                    ->suggestions([
                                                        'email',
                                                        'url',
                                                        'numeric',
                                                        'min:1',
                                                        'max:255',
                                                        'regex:/^[a-z]+$/i',
                                                        'alpha',
                                                        'alpha_num',
                                                        'confirmed',
                                                        'uuid',
                                                    ]),
                                            ])
                                            ->columns(5)
                                            ->addActionLabel('Add New Column')
                                            ->grid(1)
                                            ->defaultItems(1),
                                    ]),
                            ]),

                        Tabs\Tab::make('Advanced')
                            ->icon('heroicon-o-code-bracket')
                            ->schema([
                                Forms\Components\CodeEditor::make('settings')
                                    ->label('Settings (JSON)')
                                    ->helperText('Advanced config in JSON format.')
                                    ->language(Language::Json),
                            ]),

                        Tabs\Tab::make('Access Rules')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Row Level Security (RLS)')
                                    ->description('Define who can access data in this table. Select a preset or choose "Custom" for advanced rules.')
                                    ->schema([
                                        Forms\Components\Select::make('list_rule')
                                            ->label('📋 List (View All)')
                                            ->options([
                                                '' => '🔒 Admin Only (Default)',
                                                'true' => '🌐 Public (Anyone)',
                                                'auth.id != null' => '🔑 Authenticated Users',
                                                'auth.id == user_id' => '👤 Owner Only',
                                            ])
                                            ->helperText('Who can view the collection of records'),

                                        Forms\Components\Select::make('view_rule')
                                            ->label('👁️ View (Single Record)')
                                            ->options([
                                                '' => '🔒 Admin Only (Default)',
                                                'true' => '🌐 Public (Anyone)',
                                                'auth.id != null' => '🔑 Authenticated Users',
                                                'auth.id == user_id' => '👤 Owner Only',
                                            ])
                                            ->helperText('Who can view a single record'),

                                        Forms\Components\Select::make('create_rule')
                                            ->label('➕ Create')
                                            ->options([
                                                '' => '🔒 Admin Only (Default)',
                                                'true' => '🌐 Public (Anyone)',
                                                'auth.id != null' => '🔑 Authenticated Users',
                                            ])
                                            ->helperText('Who can create new records'),

                                        Forms\Components\Select::make('update_rule')
                                            ->label('✏️ Update')
                                            ->options([
                                                '' => '🔒 Admin Only (Default)',
                                                'true' => '🌐 Public (Anyone)',
                                                'auth.id != null' => '🔑 Authenticated Users',
                                                'auth.id == user_id' => '👤 Owner Only',
                                            ])
                                            ->helperText('Who can update records'),

                                        Forms\Components\Select::make('delete_rule')
                                            ->label('🗑️ Delete')
                                            ->options([
                                                '' => '🔒 Admin Only (Default)',
                                                'auth.id != null' => '🔑 Authenticated Users',
                                                'auth.id == user_id' => '👤 Owner Only',
                                            ])
                                            ->helperText('Who can delete records'),
                                    ])->columns(2),
                            ]),
                    ])->columnSpanFull(),

                // Live Data Preview Panel (only show on edit with existing record)
                Section::make('Live Data Preview')
                    ->icon('heroicon-o-table-cells')
                    ->description('Real-time preview of your table data')
                    ->visible(fn ($record) => $record !== null)
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('preview')
                            ->content(function ($record) {
                                if (! $record) {
                                    return 'No preview available';
                                }

                                return view('filament.components.data-nexus-live-preview', [
                                    'dynamicModel' => $record,
                                    'tableName' => $record->table_name,
                                    'isPreviewOpen' => false,
                                ]);
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('display_name')
                                ->weight('bold')
                                ->size('lg')
                                ->searchable()
                                ->sortable()
                                ->icon('heroicon-o-table-cells')
                                ->iconColor('primary'),
                        ])->space(1),

                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('quick_actions')
                                ->label('')
                                ->formatStateUsing(fn () => '')
                                ->extraAttributes(['class' => 'flex gap-2 justify-end']),
                            Tables\Columns\TextColumn::make('table_name')
                                ->badge()
                                ->color('gray')
                                ->icon('heroicon-o-circle-stack')
                                ->size('sm'),
                        ])->alignment('end')->space(1),
                    ]),

                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('stats')
                                ->label('Statistics')
                                ->formatStateUsing(function (DynamicModel $record) {
                                    $recordCount = 0;
                                    $lastActivity = null;

                                    try {
                                        if (DbSchema::hasTable($record->table_name)) {
                                            $recordCount = \Illuminate\Support\Facades\DB::table($record->table_name)->count();

                                            if ($record->has_timestamps) {
                                                $lastRecord = \Illuminate\Support\Facades\DB::table($record->table_name)
                                                    ->orderBy('updated_at', 'desc')
                                                    ->first();
                                                if ($lastRecord && isset($lastRecord->updated_at)) {
                                                    $lastActivity = \Carbon\Carbon::parse($lastRecord->updated_at)->diffForHumans();
                                                }
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        // Table might not exist yet
                                    }

                                    $stats = [];
                                    $stats[] = "📊 {$recordCount} records";
                                    $stats[] = "📋 {$record->fields->count()} fields";
                                    if ($lastActivity) {
                                        $stats[] = "🕐 Updated {$lastActivity}";
                                    }

                                    return implode(' • ', $stats);
                                })
                                ->color('gray')
                                ->size('sm'),
                        ]),

                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('badges')
                                ->formatStateUsing(function (DynamicModel $record) {
                                    $badges = [];

                                    if ($record->is_active) {
                                        $badges[] = '✅ Active';
                                    } else {
                                        $badges[] = '⏸️ Inactive';
                                    }

                                    if ($record->generate_api) {
                                        $badges[] = '🔌 API';
                                    }

                                    if ($record->has_timestamps) {
                                        $badges[] = '⏰ Timestamps';
                                    }

                                    if ($record->has_soft_deletes) {
                                        $badges[] = '🗑️ Soft Delete';
                                    }

                                    return implode(' ', $badges);
                                })
                                ->size('xs')
                                ->color('gray'),
                        ])->alignment('end'),
                    ]),
                ])->space(2),
            ])
            ->contentGrid([
                'md' => 1,
                'xl' => 1,
            ])
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading('No Models Built Yet')
            ->emptyStateDescription('Start by building your first dynamic database table.')
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All Tables')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),

                Tables\Filters\TernaryFilter::make('generate_api')
                    ->label('API Status')
                    ->placeholder('All Tables')
                    ->trueLabel('API Enabled')
                    ->falseLabel('API Disabled'),

                Tables\Filters\SelectFilter::make('has_timestamps')
                    ->label('Features')
                    ->options([
                        '1' => 'With Timestamps',
                        '0' => 'Without Timestamps',
                    ]),
            ])
            ->actions([
                Action::make('view_data')
                    ->label('Explore')
                    ->icon('heroicon-o-table-cells')
                    ->color('info')
                    ->button()
                    ->outlined()
                    ->url(fn (DynamicModel $record) => \App\Filament\Pages\DataExplorer::getUrl(['table' => $record->table_name]))
                    ->openUrlInNewTab(),

                Action::make('api_docs')
                    ->label('API Docs')
                    ->icon('heroicon-o-book-open')
                    ->color('info')
                    ->button()
                    ->outlined()
                    ->url(fn (DynamicModel $record) => \App\Filament\Pages\ApiDocumentation::getUrl(['model' => $record->id]))
                    ->openUrlInNewTab(),

                Action::make('json_preview')
                    ->label('JSON Schema')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->button()
                    ->outlined()
                    ->modalHeading(fn (DynamicModel $record) => $record->display_name.' - JSON Schema')
                    ->modalContent(function (DynamicModel $record) {
                        return view('filament.components.json-preview', [
                            'json' => json_encode($record->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                        ]);
                    })
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('export_schema')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->button()
                    ->outlined()
                    ->url(fn (DynamicModel $record) => route('filament.admin.resources.dynamic-models.export-schema', ['record' => $record]))
                    ->visible(false), // Disable for now or verify route exists

                EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit Table'),

                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete Table')
                    ->modalHeading(fn ($record) => "Delete {$record->name} Table?")
                    ->modalDescription('⚠️ WARNING: This will permanently delete the table structure AND ALL DATA stored within it. API endpoints for this table will immediately stop working. This cannot be undone.')
                    ->modalSubmitActionLabel('Yes, Nuke it 💥')
                    ->requiresConfirmation()
                    ->action(function (DynamicModel $record) {
                        // Ensure table is dropped too
                        DbSchema::dropIfExists($record->table_name);
                        $record->delete();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (DynamicModel $record) => \App\Filament\Pages\DataExplorer::getUrl(['tableId' => $record->id]))
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RelationshipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDynamicModels::route('/'),
            'create' => Pages\CreateDynamicModel::route('/create'),
            'edit' => Pages\EditDynamicModel::route('/{record}/edit'),
        ];
    }
}
