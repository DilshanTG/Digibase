<?php

namespace App\Filament\Pages;

use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

use BackedEnum;
use UnitEnum;

class Backups extends BaseBackups
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server';

    protected string $view = 'filament.pages.backups';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring & Logs';

    public static function getNavigationSort(): ?int
    {
        return 98;
    }
}
