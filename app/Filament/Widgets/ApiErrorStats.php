<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApiErrorStats extends BaseWidget
{
    protected ?string $pollingInterval = '15s';

    protected static ?int $sort = 11;

    protected function getStats(): array
    {
        $today = Carbon::today();

        // Single query to get all three metrics at once
        $metrics = DB::table('api_analytics')
            ->where('created_at', '>=', $today)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors')
            ->selectRaw('AVG(duration_ms) as avg_latency')
            ->first();

        $total = $metrics->total ?? 0;
        $errors = $metrics->errors ?? 0;
        $avgLatency = round($metrics->avg_latency ?? 0, 1);
        $errorRate = $total > 0 ? round(($errors / $total) * 100, 1) : 0;

        // Determine error rate color/description
        $errorColor = match (true) {
            $errorRate > 10 => 'danger',
            $errorRate > 5 => 'warning',
            default => 'success',
        };

        $errorDescription = match (true) {
            $errorRate > 10 => 'Above threshold — investigate',
            $errorRate > 5 => 'Slightly elevated',
            default => 'Healthy',
        };

        // Determine latency color
        $latencyColor = match (true) {
            $avgLatency > 500 => 'danger',
            $avgLatency > 200 => 'warning',
            default => 'success',
        };

        return [
            Stat::make('Total Requests (Today)', number_format($total))
                ->description('Since midnight')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),

            Stat::make('Error Rate', "{$errorRate}%")
                ->description("{$errors} errors — {$errorDescription}")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($errorColor),

            Stat::make('Avg Latency', "{$avgLatency} ms")
                ->description('Mean response time today')
                ->descriptionIcon('heroicon-m-clock')
                ->color($latencyColor),
        ];
    }
}
