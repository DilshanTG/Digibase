<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApiTrafficChart extends ChartWidget
{
    protected static ?string $heading = 'API Traffic (Last 24 Hours)';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '15s';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $since = Carbon::now()->subHours(24);

        // Build a map of hour => count from actual data
        $rows = DB::table('api_analytics')
            ->select(
                DB::raw("strftime('%Y-%m-%d %H:00', created_at) as hour_bucket"),
                DB::raw('COUNT(*) as hits')
            )
            ->where('created_at', '>=', $since)
            ->groupBy('hour_bucket')
            ->orderBy('hour_bucket')
            ->pluck('hits', 'hour_bucket')
            ->toArray();

        // Generate all 24 hour slots so the chart has no gaps
        $labels = [];
        $data = [];

        for ($i = 23; $i >= 0; $i--) {
            $hour = Carbon::now()->subHours($i);
            $bucket = $hour->format('Y-m-d H:00');
            $labels[] = $hour->format('H:00');
            $data[] = $rows[$bucket] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'API Hits',
                    'data' => $data,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
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
