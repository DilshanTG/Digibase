<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\ApiAnalytics;

class ApiErrorStats extends ChartWidget
{
    protected ?string $heading = 'Failed Requests (Last 7 Days)';

    protected static ?int $sort = 11;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $data = ApiAnalytics::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('status_code', '>=', 400) // Filter only errors
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        return [
            'datasets' => [
                [
                    'label' => 'Failed Requests (Errors)',
                    'data' => $data->values()->toArray(),
                    'borderColor' => '#ef4444', // Red color for errors
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->keys()->toArray(),
        ];
    }
}
