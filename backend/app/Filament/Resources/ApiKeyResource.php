<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiKeyResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use Laravel\Sanctum\PersonalAccessToken;
use UnitEnum;

class ApiKeyResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'API Keys';

    protected static ?string $modelLabel = 'API Key';

    protected static string|UnitEnum|null $navigationGroup = 'Admin';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Generate API Token')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Token Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Mobile App, CI/CD Pipeline'),

                        Forms\Components\Select::make('tokenable_id')
                            ->label('User')
                            ->relationship('tokenable', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\CheckboxList::make('abilities')
                            ->options([
                                '*' => 'Full Access (All Permissions)',
                                'read' => 'Read Only',
                                'create' => 'Create Records',
                                'update' => 'Update Records',
                                'delete' => 'Delete Records',
                            ])
                            ->default(['*'])
                            ->columns(3),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Token Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tokenable.name')
                    ->label('User')
                    ->searchable(),

                Tables\Columns\TextColumn::make('abilities')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('Never')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Revoke'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Revoke Selected'),
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
            'index' => Pages\ListApiKeys::route('/'),
            'create' => Pages\CreateApiKey::route('/create'),
        ];
    }
}
