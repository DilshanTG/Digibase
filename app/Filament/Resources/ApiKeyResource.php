<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use App\Models\DynamicModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use BackedEnum;
use UnitEnum;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'API Keys';

    protected static ?string $modelLabel = 'API Key';

    protected static string|UnitEnum|null $navigationGroup = 'Developers';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Key Configuration')
                    ->description('Configure your API key settings')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Key Name')
                            ->placeholder('e.g., Mobile App, Frontend, Partner Integration')
                            ->required()
                            ->maxLength(255)
                            ->helperText('A friendly name to identify this key'),

                        Forms\Components\Select::make('type')
                            ->label('Key Type')
                            ->options([
                                'public' => '🔓 Public (Read-Only) - pk_xxx',
                                'secret' => '🔐 Secret (Full Access) - sk_xxx',
                            ])
                            ->default('public')
                            ->required()
                            ->live()
                            ->helperText('Public keys can only read. Secret keys can create, update, and delete.')
                            ->disabledOn('edit'),

                        Forms\Components\CheckboxList::make('scopes')
                            ->label('Permissions')
                            ->options([
                                'read' => '📖 Read - View & list data',
                                'write' => '✏️ Write - Create & update data',
                                'delete' => '🗑️ Delete - Remove data',
                            ])
                            ->default(fn ($get) => $get('type') === 'secret' 
                                ? ['read', 'write', 'delete'] 
                                : ['read'])
                            ->columns(3)
                            ->helperText('Select what this key can do'),

                        Forms\Components\Select::make('allowed_tables')
                            ->label('Allowed Tables')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                return DynamicModel::where('is_active', true)
                                    ->pluck('display_name', 'table_name')
                                    ->toArray();
                            })
                            ->helperText('Leave empty to allow access to ALL tables. Select specific tables to restrict.'),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expiration Date')
                            ->nullable()
                            ->minDate(now()->addHour())
                            ->helperText('Leave empty for no expiration'),

                        Forms\Components\TextInput::make('rate_limit')
                            ->label('Rate Limit (requests/minute)')
                            ->numeric()
                            ->default(60)
                            ->minValue(1)
                            ->maxValue(1000)
                            ->helperText('Maximum API calls per minute'),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Deactivate to temporarily disable this key'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-key'),

                Tables\Columns\TextColumn::make('masked_key')
                    ->label('Key')
                    ->copyable()
                    ->copyableState(fn ($record) => $record->key)
                    ->copyMessage('🔑 API Key copied!')
                    ->fontFamily('mono')
                    ->color('gray'),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'gray' => 'public',
                        'danger' => 'secret',
                    ])
                    ->icons([
                        'heroicon-o-lock-open' => 'public',
                        'heroicon-o-lock-closed' => 'secret',
                    ]),

                Tables\Columns\TextColumn::make('scopes')
                    ->label('Scopes')
                    ->badge()
                    ->separator(',')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('allowed_tables')
                    ->label('Tables')
                    ->badge()
                    ->separator(',')
                    ->color('warning')
                    ->placeholder('All Tables')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('Never')
                    ->sortable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y')
                    ->placeholder('Never')
                    ->sortable()
                    ->color(fn ($record) => $record->expires_at?->isPast() ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'public' => 'Public Keys',
                        'secret' => 'Secret Keys',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->boolean()
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions([
                Actions\Action::make('copy')
                    ->label('Copy Key')
                    ->icon('heroicon-o-clipboard')
                    ->color('gray')
                    ->action(fn () => null) // Handled by JS
                    ->extraAttributes(fn ($record) => [
                        'x-on:click' => "navigator.clipboard.writeText('{$record->key}'); \$tooltip('Copied!')",
                    ]),
                Actions\Action::make('toggle')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn ($record) => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['is_active' => !$record->is_active])),
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->label('Revoke'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Revoke Selected'),
                ]),
            ])
            ->emptyStateHeading('No API Keys')
            ->emptyStateDescription('Create your first API key to start using the API.')
            ->emptyStateIcon('heroicon-o-key');
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
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }
}
