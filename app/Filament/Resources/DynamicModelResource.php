<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DynamicModelResource\Pages;
use App\Filament\Resources\DynamicModelResource\RelationManagers;
use App\Models\DynamicModel;
use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Database\Schema\Blueprint;
use BackedEnum;
use UnitEnum;
use Illuminate\Support\Str;

class DynamicModelResource extends Resource
{
    protected static ?string $model = DynamicModel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationLabel = 'Table Builder';
    protected static ?string $modelLabel = 'Database Table';
    protected static string|UnitEnum|null $navigationGroup = 'Database';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Tabs::make('Table Configuration')
                    ->tabs([
                        Tabs\Tab::make('Definition')
                            ->icon('heroicon-o-table-cells')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Table Name (SQL)')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. blog_posts')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        if ($state) {
                                            $slug = Str::slug($state, '_');
                                            $set('name', $slug);
                                            $set('table_name', $slug);
                                            $set('display_name', Str::headline($state));
                                        }
                                    })
                                    ->helperText('This will be the physical table name in your database.'),

                                Forms\Components\TextInput::make('display_name')
                                    ->label('Display Name')
                                    ->placeholder('e.g. Blog Posts')
                                    ->maxLength(255),

                                Forms\Components\Hidden::make('table_name'),
                            ])->columns(2),

                        Tabs\Tab::make('Configuration')
                            ->icon('heroicon-o-cog')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                Forms\Components\Toggle::make('generate_api')
                                    ->label('Generate API')
                                    ->default(true),
                                Forms\Components\Toggle::make('has_timestamps')
                                    ->label('Timestamps (created_at, updated_at)')
                                    ->default(true),
                                Forms\Components\Toggle::make('has_soft_deletes')
                                    ->label('Soft Deletes (deleted_at)')
                                    ->default(false),
                            ])->columns(2),

                        Tabs\Tab::make('Columns')
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                Forms\Components\Repeater::make('fields')
                                    ->relationship('fields')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Column Name')
                                            ->required()
                                            ->placeholder('e.g. title')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Set $set, ?string $state) =>
                                                $set('display_name', $state ? Str::headline($state) : '')
                                            ),

                                        Forms\Components\Hidden::make('display_name'),

                                        Forms\Components\Select::make('type')
                                            ->options([
                                                'string' => 'String (Short Text)',
                                                'text' => 'Text (Long Description)',
                                                'integer' => 'Integer (Number)',
                                                'boolean' => 'Boolean (True/False)',
                                                'date' => 'Date',
                                                'datetime' => 'Date & Time',
                                                'json' => 'JSON / Object',
                                                'file' => 'File / Image',
                                            ])
                                            ->required(),

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
                                    ->defaultItems(1)
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
                    ])->columnSpanFull()
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
                                            $recordCount = \DB::table($record->table_name)->count();
                                            
                                            if ($record->has_timestamps) {
                                                $lastRecord = \DB::table($record->table_name)
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
                // Quick Actions (shown on the right side of cards)
                Action::make('view_data')
                    ->label('View Data')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->button()
                    ->outlined()
                    ->url(fn (DynamicModel $record) => \App\Filament\Pages\DataExplorer::getUrl(['tableId' => $record->id])),
                    
                Action::make('spreadsheet_edit')
                    ->label('Spreadsheet')
                    ->icon('heroicon-o-squares-2x2')
                    ->color('warning')
                    ->button()
                    ->outlined()
                    ->url(fn (DynamicModel $record) => \App\Filament\Pages\DataExplorer::getUrl(['tableId' => $record->id, 'spreadsheet' => true]))
                    ->tooltip('Edit data in spreadsheet view with Univer.js'),
                    
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
                    ->modalHeading(fn (DynamicModel $record) => $record->display_name . ' - JSON Schema')
                    ->modalContent(function (DynamicModel $record) {
                        $schema = [
                            'table' => $record->table_name,
                            'display_name' => $record->display_name,
                            'description' => $record->description,
                            'features' => [
                                'timestamps' => $record->has_timestamps,
                                'soft_deletes' => $record->has_soft_deletes,
                                'api_enabled' => $record->generate_api,
                            ],
                            'fields' => $record->fields->map(function ($field) {
                                return [
                                    'name' => $field->name,
                                    'type' => $field->type,
                                    'display_name' => $field->display_name,
                                    'required' => $field->is_required,
                                    'unique' => $field->is_unique,
                                    'default' => $field->default_value,
                                    'validation' => $field->validation_rules,
                                ];
                            })->toArray(),
                            'security' => [
                                'list_rule' => $record->list_rule,
                                'view_rule' => $record->view_rule,
                                'create_rule' => $record->create_rule,
                                'update_rule' => $record->update_rule,
                                'delete_rule' => $record->delete_rule,
                            ],
                        ];
                        
                        return view('filament.components.json-preview', [
                            'json' => json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
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
                    ->action(function (DynamicModel $record) {
                        $schema = [
                            'table' => $record->table_name,
                            'display_name' => $record->display_name,
                            'description' => $record->description,
                            'features' => [
                                'timestamps' => $record->has_timestamps,
                                'soft_deletes' => $record->has_soft_deletes,
                                'api_enabled' => $record->generate_api,
                            ],
                            'fields' => $record->fields->map(function ($field) {
                                return [
                                    'name' => $field->name,
                                    'type' => $field->type,
                                    'display_name' => $field->display_name,
                                    'required' => $field->is_required,
                                    'unique' => $field->is_unique,
                                    'default' => $field->default_value,
                                    'validation' => $field->validation_rules,
                                ];
                            })->toArray(),
                            'security' => [
                                'list_rule' => $record->list_rule,
                                'view_rule' => $record->view_rule,
                                'create_rule' => $record->create_rule,
                                'update_rule' => $record->update_rule,
                                'delete_rule' => $record->delete_rule,
                            ],
                        ];
                        
                        $filename = 'schema-' . $record->table_name . '.json';
                        
                        return response()->streamDownload(function () use ($schema) {
                            echo json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        }, $filename, [
                            'Content-Type' => 'application/json',
                        ]);
                    }),
                    
                // Divider
                Action::make('divider_1')
                    ->label('')
                    ->disabled()
                    ->extraAttributes(['class' => 'border-l border-gray-300 dark:border-gray-600 h-8 mx-2']),
                
                // Icon Actions (existing)
                Action::make('sync_db')
                    ->icon('heroicon-o-arrow-path')
                    ->iconButton()
                    ->tooltip('Sync Database Schema')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (DynamicModel $record) {
                        $tableName = $record->table_name;
                        
                        if (!DbSchema::hasTable($tableName)) {
                            // CREATE NEW TABLE
                            DbSchema::create($tableName, function (Blueprint $table) use ($record) {
                                $table->id();
                                foreach ($record->fields as $field) {
                                    $type = $field->getDatabaseType();
                                    $column = $table->{$type}($field->name);
                                    if (!$field->is_required || in_array($field->type, ['file', 'image'])) $column->nullable();
                                    if ($field->is_unique) $column->unique();
                                }
                                $table->timestamps();
                                
                                // CRITICAL: Add soft deletes column if enabled
                                if ($record->has_soft_deletes) {
                                    $table->softDeletes();
                                }
                            });
                            Notification::make()->success()->title('Table Created')->send();
                        } else {
                            // UPDATE EXISTING TABLE - Add missing columns
                            $columnsAdded = 0;
                            
                            DbSchema::table($tableName, function (Blueprint $table) use ($record, $tableName, &$columnsAdded) {
                                foreach ($record->fields as $field) {
                                    if (!DbSchema::hasColumn($tableName, $field->name)) {
                                        $type = $field->getDatabaseType();
                                        $column = $table->{$type}($field->name);
                                        if (!$field->is_required || in_array($field->type, ['file', 'image'])) $column->nullable();
                                        $columnsAdded++;
                                    }
                                }
                                
                                // Add soft deletes if enabled and column doesn't exist
                                if ($record->has_soft_deletes && !DbSchema::hasColumn($tableName, 'deleted_at')) {
                                    $table->softDeletes();
                                    $columnsAdded++;
                                }
                            });
                            
                            if ($columnsAdded > 0) {
                                Notification::make()->success()->title("{$columnsAdded} column(s) added")->send();
                            } else {
                                Notification::make()->info()->title('Schema is up to date')->send();
                            }
                        }
                    }),
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit Table'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete Table'),
                Action::make('destroy_table')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->iconButton()
                    ->tooltip('Destroy DB Table (Nuclear Option)')
                    ->requiresConfirmation()
                    ->modalHeading('⚠️ NUCLEAR OPTION: Destroy Database Table')
                    ->modalDescription('This will PERMANENTLY DELETE the physical table and ALL DATA. This cannot be undone.')
                    ->form([
                        Forms\Components\TextInput::make('confirmation_name')
                            ->label(fn ($record) => "Type '{$record->table_name}' to confirm")
                            ->required()
                            ->rule(fn ($record) => function ($attribute, $value, $fail) use ($record) {
                                if ($value !== $record->table_name) {
                                    $fail('The table name does not match.');
                                }
                            }),
                    ])
                    ->action(function ($record, array $data) {
                        // 1. Drop the Physical Table
                        DbSchema::dropIfExists($record->table_name);
                        
                        // 2. Delete the Dynamic Model Record
                        $record->delete();

                        Notification::make()
                            ->title('Table Destroyed')
                            ->body("The table '{$record->table_name}' has been wiped from the database.")
                            ->danger()
                            ->send();
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
