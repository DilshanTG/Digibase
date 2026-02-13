<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring & Logs';

    protected static ?string $modelLabel = 'Activity Log';

    protected static ?string $pluralModelLabel = 'Activity Logs';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('created_at', '>=', now()->subDay())->count();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->id() === 1;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading('No Activity Recorded')
            ->emptyStateDescription('Audit logs will appear here as you interact with the system.')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('event_icon')
                    ->label('')
                    ->icon(fn ($record) => match ($record->event) {
                        'created', 'create' => 'heroicon-o-plus-circle',
                        'updated', 'update' => 'heroicon-o-pencil',
                        'deleted', 'delete' => 'heroicon-o-trash',
                        'restored', 'restore' => 'heroicon-o-arrow-uturn-left',
                        'force_deleted' => 'heroicon-o-fire',
                        default => 'heroicon-o-information-circle',
                    })
                    ->color(fn ($record) => match ($record->event) {
                        'created', 'create' => 'success',
                        'updated', 'update' => 'info',
                        'deleted', 'delete' => 'danger',
                        'restored', 'restore' => 'warning',
                        'force_deleted' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('event')
                    ->badge()
                    ->color(fn ($record) => match ($record->event) {
                        'created', 'create' => 'success',
                        'updated', 'update' => 'info',
                        'deleted', 'delete' => 'danger',
                        'restored', 'restore' => 'warning',
                        'force_deleted' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('log_name')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject_display')
                    ->label('Subject')
                    ->state(function ($record) {
                        $subjectType = $record->subject_type;

                        // Handle cases where subject_type is not a valid class (e.g., dynamic table names)
                        if ($subjectType && ! class_exists($subjectType)) {
                            return Str::studly($subjectType).' #'.$record->subject_id;
                        }

                        if (! $record->subject) {
                            return $subjectType
                                ? Str::afterLast($subjectType, '\\').' #'.$record->subject_id
                                : 'N/A';
                        }
                        $class = Str::afterLast($subjectType, '\\');
                        $identifier = $record->subject->name ?? $record->subject->title ?? $record->subject->id;

                        return "{$class}: {$identifier}";
                    })
                    ->searchable(query: function ($query, $search) {
                        $query->where('subject_type', 'like', "%{$search}%")
                            ->orWhere('subject_id', 'like', "%{$search}%");
                    })
                    ->tooltip(fn ($record) => $record->subject_type ? $record->subject_type : null),

                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->wrap(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->searchable()
                    ->placeholder('System')
                    ->icon('heroicon-o-user')
                    ->color(fn ($record) => $record->causer ? 'primary' : 'gray'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->since()
                    ->sortable()
                    ->tooltip(fn ($record) => $record->created_at?->format('Y-m-d H:i:s')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'restored' => 'Restored',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('log_name')
                    ->options(function () {
                        return Activity::distinct()->pluck('log_name', 'log_name')->toArray();
                    })
                    ->multiple(),

            ])
            ->actions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip('View Details')
                    ->modalHeading(fn ($record) => 'Activity Log #'.$record->id.' Details')
                    ->modalContent(function (Activity $record) {
                        return view('filament.components.activity-log-modal', [
                            'log' => $record,
                        ]);
                    })
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageActivityLogs::route('/'),
        ];
    }
}
