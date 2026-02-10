<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TopTablesWidget extends ChartWidget
{
    protected static ?string $heading = 'Most Accessed Tables (Last 7 Days)';

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '15s';

    protected static ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $since = Carbon::now()->subDays(7);

        $rows = DB::table('api_analytics')
            ->select('table_name', DB::raw('COUNT(*) as hits'))
            ->where('created_at', '>=', $since)
            ->where('table_name', '!=', '-')
            ->groupBy('table_name')
            ->orderByDesc('hits')
            ->limit(10)
            ->get();

        $colors = [
            '#6366f1', '#8b5cf6', '#a78bfa', '#c4b5fd',
            '#818cf8', '#4f46e5', '#4338ca', '#3730a3',
            '#312e81', '#1e1b4b',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Hits',
                    'data' => $rows->pluck('hits')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $rows->count()),
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $rows->pluck('table_name')->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
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
