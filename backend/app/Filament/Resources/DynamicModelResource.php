<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DynamicModelResource\Pages;
use App\Models\DynamicModel;
use Filament\Forms;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use Illuminate\Support\Str;

class DynamicModelResource extends Resource
{
    protected static ?string $model = DynamicModel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationLabel = 'Table Builder';
    protected static ?string $modelLabel = 'Database Table';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Table Details')
                    ->description('Define the main details of your database table.')
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

                Forms\Components\Section::make('Table Columns')
                    ->description('Add columns to your table visually.')
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
                                    ->label('Unique Value')
                                    ->default(false),
                            ])
                            ->columns(5)
                            ->addActionLabel('Add New Column')
                            ->grid(1)
                            ->defaultItems(1)
                    ]),

                Forms\Components\Section::make('Advanced')
                    ->description('Custom settings as JSON (optional).')
                    ->collapsed()
                    ->schema([
                        Forms\Components\CodeEditor::make('settings')
                            ->label('Settings (JSON)')
                            ->helperText('Advanced config in JSON format.')
                            ->language('json'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextInputColumn::make('name')
                    ->label('Table Name')
                    ->searchable()
                    ->sortable()
                    ->rules(['required', 'max:255']),

                Tables\Columns\TextInputColumn::make('display_name')
                    ->label('Display Name')
                    ->searchable()
                    ->sortable()
                    ->rules(['required', 'max:255']),

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

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('generate_api')
                    ->label('API')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('has_timestamps')
                    ->label('Timestamps')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\ToggleColumn::make('has_soft_deletes')
                    ->label('Soft Delete')
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [];
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
