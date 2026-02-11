<?php

namespace App\Filament\Resources\Webhooks;

use App\Filament\Resources\Webhooks\Pages\ManageWebhooks;
use App\Models\DynamicModel;
use App\Models\Webhook;
use BackedEnum;
use UnitEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;

class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?string $navigationLabel = 'Webhooks';
    protected static ?string $modelLabel = 'Webhook';
    protected static ?string $pluralModelLabel = 'Webhooks';
    protected static string|UnitEnum|null $navigationGroup = 'API & Integration';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Main Info Section
                Section::make('Webhook Details')
                    ->icon('heroicon-o-bolt')
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\Select::make('dynamic_model_id')
                                ->label('Target Table')
                                ->options(DynamicModel::pluck('display_name', 'id'))
                                ->required()
                                ->searchable()
                                ->placeholder('Select a table...'),

                            Forms\Components\TextInput::make('name')
                                ->label('Webhook Name')
                                ->placeholder('e.g., Notify Slack')
                                ->maxLength(255),
                        ]),

                        Forms\Components\TextInput::make('url')
                            ->label('Endpoint URL')
                            ->required()
                            ->url()
                            ->placeholder('https://your-server.com/webhook')
                            ->columnSpanFull(),
                    ]),

                // Events Section
                Section::make('Trigger Events')
                    ->icon('heroicon-o-sparkles')
                    ->description('Select which events should fire this webhook')
                    ->schema([
                        Forms\Components\CheckboxList::make('events')
                            ->label('')
                            ->options([
                                'created' => 'Record Created',
                                'updated' => 'Record Updated',
                                'deleted' => 'Record Deleted',
                            ])
                            ->default(['created', 'updated', 'deleted'])
                            ->columns(3)
                            ->required(),
                    ]),

                // Security Section
                Section::make('Security')
                    ->icon('heroicon-o-shield-check')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('secret')
                            ->label('HMAC Secret')
                            ->password()
                            ->revealable()
                            ->placeholder('Optional signing key')
                            ->helperText('Payloads will be signed with X-Webhook-Signature header'),

                        Forms\Components\KeyValue::make('headers')
                            ->label('Custom Headers')
                            ->keyLabel('Header')
                            ->valueLabel('Value')
                            ->addActionLabel('Add Header'),
                    ]),

                // Status Toggle
                Forms\Components\Toggle::make('is_active')
                    ->label('Webhook Active')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('dynamicModel.display_name')
                    ->label('Table')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('—')
                    ->limit(20),

                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(30)
                    ->copyable()
                    ->tooltip(fn ($record) => $record->url),

                Tables\Columns\TextColumn::make('events')
                    ->label('Events')
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' events' : '—')
                    ->color('info'),

                Tables\Columns\IconColumn::make('has_secret')
                    ->label('Signed')
                    ->getStateUsing(fn ($record) => !empty($record->secret))
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger'),

                Tables\Columns\TextColumn::make('failure_count')
                    ->label('Failures')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 10 => 'danger',
                        $state >= 5 => 'warning',
                        $state > 0 => 'gray',
                        default => 'success',
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '✓'),

                Tables\Columns\TextColumn::make('last_triggered_at')
                    ->label('Last Trigger')
                    ->dateTime('M j, H:i')
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('dynamic_model_id')
                    ->label('Table')
                    ->options(DynamicModel::pluck('display_name', 'id')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWebhooks::route('/'),
        ];
    }
}
