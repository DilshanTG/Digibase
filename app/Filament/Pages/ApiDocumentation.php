<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\DynamicModel;
use BackedEnum;
use UnitEnum;

class ApiDocumentation extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    protected static string|UnitEnum|null $navigationGroup = 'Developer Tools';
    protected static ?string $title = 'API Reference';
    protected string $view = 'filament.pages.api-documentation';

    public function getViewData(): array
    {
        return [
            'models' => DynamicModel::all(),
            'baseUrl' => url('/api/v1'),
        ];
    }
}
