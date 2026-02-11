<?php

namespace App\Filament\Widgets;

use App\Models\DynamicModel;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalRequests = \App\Models\ApiAnalytics::count();
        $totalErrors = \App\Models\ApiAnalytics::where('status_code', '>=', 400)->count();
        $activeKeys = \App\Models\ApiKey::where('is_active', true)->count();

        return [
            \Filament\Widgets\StatsOverviewWidget\Stat::make('Total API Requests', $totalRequests)
                ->description('All time requests')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),

            \Filament\Widgets\StatsOverviewWidget\Stat::make('Failed Requests', $totalErrors)
                ->description('4xx & 5xx Errors')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            \Filament\Widgets\StatsOverviewWidget\Stat::make('Active API Keys', $activeKeys)
                ->description('Keys currently in use')
                ->color('primary'),
        ];
    }

    private function getDatabaseHealth(): string
    {
        try {
            DB::select('SELECT 1');
            return 'Healthy';
        } catch (\Exception $e) {
            return 'Error';
        }
    }
}
