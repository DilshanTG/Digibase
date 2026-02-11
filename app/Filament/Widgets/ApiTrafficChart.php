<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApiTrafficChart extends ChartWidget
{
    protected ?string $heading = 'API Traffic (Last 24 Hours)';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '15s';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        // Fetch last 7 days of data
        $data = \App\Models\ApiAnalytics::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        return [
            'datasets' => [
                [
                    'label' => 'API Requests (Last 7 Days)',
                    'data' => $data->values()->toArray(),
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                ],
            ],
            'labels' => $data->keys()->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }
}
