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
        return [
            Stat::make('Total Users', User::count())
                ->description('Registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Total Tables', DynamicModel::count())
                ->description('Dynamic models created')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('success'),

            Stat::make('Active API Tokens', PersonalAccessToken::count())
                ->description('Sanctum tokens issued')
                ->descriptionIcon('heroicon-m-key')
                ->color('warning'),

            Stat::make('Database', $this->getDatabaseHealth())
                ->description('System health')
                ->descriptionIcon('heroicon-m-heart')
                ->color('success'),
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
