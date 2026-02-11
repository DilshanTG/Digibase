<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopTablesWidget extends BaseWidget
{
    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return \App\Models\ApiAnalytics::query()
            ->select('table_name', DB::raw('count(*) as total_requests'))
            ->whereNotNull('table_name')
            ->where('table_name', '!=', '-')
            ->groupBy('table_name')
            ->orderByDesc('total_requests')
            ->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('table_name')
                ->label('Table Name')
                ->description('Database table name'),
            Tables\Columns\TextColumn::make('total_requests')
                ->label('Hits')
                ->sortable()
                ->badge()
                ->color('success'),
        ];
    }
}
