<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTask;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;

use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;

class ScheduleMonitor extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static string|UnitEnum|null $navigationGroup = 'Monitoring & Logs';
    protected static ?int $navigationSort = 98;
    protected static ?string $navigationLabel = 'Schedule Monitor';
    protected static ?string $title = 'Schedule Monitor';
    protected string $view = 'filament.pages.schedule-monitor';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MonitoredScheduledTask::query())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Task')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-m-command-line'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'command' => 'primary',
                        'job' => 'warning',
                        'shell' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('cron_expression')
                    ->label('Schedule')
                    ->fontFamily(FontFamily::Mono)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('last_started_at')
                    ->label('Last Run')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('last_finished_at')
                    ->label('Finished At')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('Never'),

                Tables\Columns\IconColumn::make('last_failed_at')
                    ->label('Status')
                    ->icon(fn ($record) => $this->getStatusIcon($record))
                    ->color(fn ($record) => $this->getStatusColor($record))
                    ->tooltip(fn ($record) => $this->getStatusTooltip($record)),

                Tables\Columns\TextColumn::make('grace_time_in_minutes')
                    ->label('Grace (min)')
                    ->alignCenter()
                    ->placeholder('—'),
            ])
            ->defaultSort('last_started_at', 'desc')
            ->emptyStateHeading('No monitored tasks')
            ->emptyStateDescription('Run `php artisan schedule-monitor:sync` to register your scheduled tasks.')
            ->emptyStateIcon('heroicon-o-clock')
            ->poll('30s')
            ->actions([
                Action::make('view_logs')
                    ->label('Logs')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->modalHeading(fn ($record) => "Logs: {$record->name}")
                    ->modalContent(fn ($record) => view('filament.pages.schedule-monitor-logs', [
                        'logs' => MonitoredScheduledTaskLogItem::where('monitored_scheduled_task_id', $record->id)
                            ->latest()
                            ->limit(20)
                            ->get(),
                    ]))
                    ->modalWidth('xl')
                    ->modalSubmitAction(false),
            ])
            ->headerActions([
                Action::make('sync')
                    ->label('Sync Tasks')
                    ->icon('heroicon-m-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        \Artisan::call('schedule-monitor:sync');
                        \Filament\Notifications\Notification::make()
                            ->title('Tasks synced successfully')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    private function getStatusIcon($record): string
    {
        if ($record->last_failed_at && (!$record->last_finished_at || $record->last_failed_at > $record->last_finished_at)) {
            return 'heroicon-o-x-circle';
        }
        if ($record->last_finished_at) {
            return 'heroicon-o-check-circle';
        }
        if ($record->last_started_at && !$record->last_finished_at) {
            return 'heroicon-o-arrow-path';
        }
        return 'heroicon-o-minus-circle';
    }

    private function getStatusColor($record): string
    {
        if ($record->last_failed_at && (!$record->last_finished_at || $record->last_failed_at > $record->last_finished_at)) {
            return 'danger';
        }
        if ($record->last_finished_at) {
            return 'success';
        }
        if ($record->last_started_at && !$record->last_finished_at) {
            return 'warning';
        }
        return 'gray';
    }

    private function getStatusTooltip($record): string
    {
        if ($record->last_failed_at && (!$record->last_finished_at || $record->last_failed_at > $record->last_finished_at)) {
            return 'Failed at ' . $record->last_failed_at->format('M j, Y H:i');
        }
        if ($record->last_finished_at) {
            return 'Succeeded at ' . $record->last_finished_at->format('M j, Y H:i');
        }
        if ($record->last_started_at && !$record->last_finished_at) {
            return 'Running since ' . $record->last_started_at->format('M j, Y H:i');
        }
        return 'Never executed';
    }
}
