<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiKeyResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
                Section::make('Token Details')
                    ->schema([
                        // 1. Select User manually (Don't use ->relationship!)
                        Forms\Components\Select::make('tokenable_id')
                            ->label('User')
                            ->options(\App\Models\User::pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        // 2. Token Name
                        Forms\Components\TextInput::make('name')
                            ->label('Token Name (e.g. Mobile App)')
                            ->required(),

                        // 3. Abilities
                        Forms\Components\CheckboxList::make('abilities')
                            ->label('Permissions')
                            ->options([
                                '*' => 'Full Access (*)',
                                'read' => 'Read Only',
                                'create' => 'Create',
                                'update' => 'Update',
                                'delete' => 'Delete',
                            ])
                            ->default(['*'])
                            ->columns(3),
                    ])
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
                DeleteAction::make()
                    ->label('Revoke'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
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
