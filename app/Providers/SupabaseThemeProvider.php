<?php

namespace App\Providers;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class SupabaseThemeProvider extends ServiceProvider
{
    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('supabase-theme', __DIR__.'/../../resources/css/supabase-theme.css'),
        ]);
    }
}
