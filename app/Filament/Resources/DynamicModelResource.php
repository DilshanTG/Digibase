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
                                    ])
                                    ->columns(5)
                                    ->addActionLabel('Add New Column')
                                    ->grid(1)
                                    ->defaultItems(1)
                            ]),

                        Tabs\Tab::make('Relationships')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Forms\Components\Repeater::make('relationships')
                                    ->relationship('relationships')
                                    ->schema([
                                        Forms\Components\Select::make('type')
                                            ->label('Relationship Type')
                                            ->options([
                                                'hasOne' => 'Has One',
                                                'hasMany' => 'Has Many',
                                                'belongsTo' => 'Belongs To',
                                            ])
                                            ->required()
                                            ->live(),

                                        Forms\Components\TextInput::make('name')
                                            ->label('Method Name')
                                            ->required()
                                            ->placeholder('e.g. posts, author'),

                                        Forms\Components\Select::make('related_model_id')
                                            ->label('Related Table')
                                            ->options(\App\Models\DynamicModel::pluck('display_name', 'id'))
                                            ->required()
                                            ->searchable(),

                                        Forms\Components\TextInput::make('foreign_key')
                                            ->label('Foreign Key (Optional)')
                                            ->placeholder('Auto-detected (e.g. user_id)'),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Add Relationship')
                                    ->grid(1)
                                    ->defaultItems(0)
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Table Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('Display Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('table_name')
                    ->label('DB Table')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('fields_count')
                    ->counts('fields')
                    ->label('Columns')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('generate_api')
                    ->label('API')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('has_timestamps')
                    ->label('Timestamps')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('has_soft_deletes')
                    ->label('Soft Delete')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Owner')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\TernaryFilter::make('generate_api')
                    ->label('API Enabled'),
            ])
            ->actions([
                Action::make('view_data')
                    ->label('Data')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->url(fn (DynamicModel $record) => \App\Filament\Pages\DataExplorer::getUrl(['tableId' => $record->id])),
                Action::make('sync_db')
                    ->label('Sync')
                    ->icon('heroicon-o-arrow-path')
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
                                    if (!$field->is_required) $column->nullable();
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
                                        if (!$field->is_required) $column->nullable();
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
                EditAction::make(),
                DeleteAction::make(),
                Action::make('destroy_table')
                    ->label('Destroy DB Table')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
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
