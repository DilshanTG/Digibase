<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use App\Models\DynamicModel;
use BackedEnum;
use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'API Keys';

    protected static ?string $modelLabel = 'API Key';

    protected static string|UnitEnum|null $navigationGroup = 'API & Integration';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('is_active', true)->count();
    }

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

                        Forms\Components\TextInput::make('key')
                            ->label('Secret Key')
                            ->password()
                            ->revealable()
                            ->readOnly()
                            ->extraInputAttributes(['readonly' => true])
                            ->visible(fn ($record) => $record !== null)
                            ->suffixAction(
                                \Filament\Actions\Action::make('copy')
                                    ->icon('heroicon-m-clipboard')
                                    ->action(fn ($state, $livewire) => $livewire->js("window.navigator.clipboard.writeText('{$state}'); \$tooltip('Copied!', { timeout: 1500 });"))
                            ),

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

                        Forms\Components\CheckboxList::make('permissions')
                            ->label('Permissions')
                            ->options([
                                'read' => 'Read (GET) - View records',
                                'create' => 'Create (POST) - Add new records',
                                'update' => 'Update (PUT/PATCH) - Modify records',
                                'delete' => 'Delete (DELETE) - Remove records',
                            ])
                            ->descriptions([
                                'read' => 'View and list data',
                                'create' => 'Add new records',
                                'update' => 'Modify existing records',
                                'delete' => 'Permanently remove records',
                            ])
                            ->columns(2)
                            ->bulkToggleable()
                            ->default(fn ($get) => $get('type') === 'secret'
                                ? ['read', 'create', 'update', 'delete']
                                : ['read'])
                            ->helperText('Select what actions this API key can perform. Leave empty for unrestricted access.'),

                        Forms\Components\ToggleButtons::make('table_access_mode')
                            ->label('Table Access Mode')
                            ->options([
                                'all' => 'All Tables',
                                'selected' => 'Selected Tables Only',
                            ])
                            ->default('all')
                            ->inline()
                            ->live()
                            ->helperText('Choose whether this key can access all tables or only specific ones'),

                        Forms\Components\CheckboxList::make('allowed_tables')
                            ->label('Allowed Tables')
                            ->options(function () {
                                return DynamicModel::where('is_active', true)
                                    ->pluck('display_name', 'table_name')
                                    ->toArray();
                            })
                            ->columns(3)
                            ->gridDirection('column')
                            ->bulkToggleable()
                            ->helperText('Select specific tables this key can access. New tables created in the future will NOT be automatically accessible - you\'ll need to edit this key to add them.')
                            ->visible(fn ($get) => $get('table_access_mode') === 'selected')
                            ->required(fn ($get) => $get('table_access_mode') === 'selected'),

                        Forms\Components\TagsInput::make('allowed_domains')
                            ->label('Allowed Domains (CORS)')
                            ->placeholder('example.com')
                            ->icon('heroicon-o-globe-alt')
                            ->helperText('Leave empty to allow all. If set, this key will ONLY accept requests from these domains (browser origins).'),

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
                    ->columnSpanFull(),

                Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Deactivate to temporarily disable this key'),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateIcon('heroicon-o-key')
            ->emptyStateHeading('No API Keys Yet')
            ->emptyStateDescription('Create your first key to start integrating your apps.')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->icon('heroicon-o-key'),

                Tables\Columns\TextColumn::make('masked_key')
                    ->label('Key')
                    ->copyable()
                    ->copyableState(fn ($record) => $record->key)
                    ->copyMessage('API Key copied!')
                    ->fontFamily('mono')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'gray',
                        'secret' => 'danger',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'public' => 'heroicon-o-lock-open',
                        'secret' => 'heroicon-o-lock-closed',
                    }),

                Tables\Columns\TextColumn::make('scopes')
                    ->label('Scopes')
                    ->badge()
                    ->separator(',')
                    ->color('info'),

                Tables\Columns\TextColumn::make('allowed_tables')
                    ->label('Tables')
                    ->badge()
                    ->separator(',')
                    ->color('warning')
                    ->placeholder('All Tables')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->since()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
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
                    ->action(fn ($record) => $record->update(['is_active' => ! $record->is_active])),
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
