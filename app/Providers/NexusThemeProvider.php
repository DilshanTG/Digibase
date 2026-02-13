<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class NexusThemeProvider extends ServiceProvider
{
    public function boot(): void
    {
        FilamentAsset::register([
            \Filament\Support\Assets\Css::make('resources/css/nexus-theme.css', 'nexus-theme'),
        ]);
    }
}
