<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DynamicModelResource\Pages;
use App\Models\DynamicModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class DynamicModelResource extends Resource
{
    protected static ?string $model = DynamicModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationLabel = 'Table Builder';
    protected static ?string $modelLabel = 'Database Table';

    public static function form(Form $form): Form
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('display_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('fields_count')
                    ->counts('fields')
                    ->label('Total Columns')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
