<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DynamicModelResource\Pages;
use App\Models\DynamicModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class DynamicModelResource extends Resource
{
    protected static ?string $model = DynamicModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Table Builder';

    protected static ?string $modelLabel = 'Table';

    protected static ?string $pluralModelLabel = 'Tables';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Table Info')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Table Name')
                            ->required()
                            ->maxLength(64)
                            ->alphaNum()
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                if ($state) {
                                    $slug = Str::snake(Str::plural($state));
                                    $set('table_name', $slug);
                                    $set('display_name', Str::headline($state));
                                }
                            }),

                        Forms\Components\TextInput::make('table_name')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->helperText('Actual database table name'),

                        Forms\Components\TextInput::make('display_name')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(500),
                    ])->columns(2),

                Forms\Components\Section::make('Options')
                    ->schema([
                        Forms\Components\Toggle::make('has_timestamps')
                            ->label('Add created_at / updated_at')
                            ->default(true),

                        Forms\Components\Toggle::make('has_soft_deletes')
                            ->label('Soft Deletes')
                            ->default(false),

                        Forms\Components\Toggle::make('generate_api')
                            ->label('Auto-generate REST API')
                            ->default(true),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(4),

                Forms\Components\Section::make('Columns')
                    ->schema([
                        Forms\Components\Repeater::make('fields')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Column Name')
                                    ->required()
                                    ->maxLength(64)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                        if ($state) {
                                            $set('display_name', Str::headline($state));
                                        }
                                    }),

                                Forms\Components\TextInput::make('display_name')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\Select::make('type')
                                    ->label('Type')
                                    ->required()
                                    ->options([
                                        'string' => 'String',
                                        'text' => 'Text',
                                        'integer' => 'Integer',
                                        'bigint' => 'Big Integer',
                                        'float' => 'Float',
                                        'decimal' => 'Decimal',
                                        'boolean' => 'Boolean',
                                        'date' => 'Date',
                                        'datetime' => 'DateTime',
                                        'json' => 'JSON',
                                        'enum' => 'Enum / Select',
                                        'file' => 'File',
                                        'image' => 'Image',
                                    ])
                                    ->default('string'),

                                Forms\Components\Toggle::make('is_required')
                                    ->label('Required')
                                    ->default(false)
                                    ->inline(false),

                                Forms\Components\Toggle::make('is_unique')
                                    ->label('Unique')
                                    ->default(false)
                                    ->inline(false),

                                Forms\Components\TextInput::make('default_value')
                                    ->label('Default')
                                    ->maxLength(255),
                            ])
                            ->columns(6)
                            ->defaultItems(1)
                            ->addActionLabel('+ Add Column')
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data, int $index): array {
                                $data['order'] = $index;
                                $data['is_searchable'] = true;
                                $data['is_filterable'] = true;
                                $data['is_sortable'] = true;
                                $data['show_in_list'] = true;
                                $data['show_in_detail'] = true;
                                return $data;
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('table_name')
                    ->label('DB Table')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('fields_count')
                    ->counts('fields')
                    ->label('Columns'),

                Tables\Columns\IconColumn::make('generate_api')
                    ->label('API')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
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
