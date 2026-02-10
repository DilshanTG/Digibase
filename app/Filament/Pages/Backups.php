<?php

namespace App\Filament\Pages;

use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

class Backups extends BaseBackups
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-circle-stack';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 98;
    }
}
