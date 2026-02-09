<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use App\Models\DynamicRecord;
use App\Observers\DynamicRecordObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 🧠 CENTRAL NERVOUS SYSTEM: Register the Observer
        DynamicRecord::observe(DynamicRecordObserver::class);

        Scramble::extendOpenApi(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer')
            );
        });

        $this->configureBranding();
    }

    private function configureBranding(): void
    {
        try {
            if (!function_exists('db_config')) return;

            // Branding
            $appName = db_config('branding.site_name');
            $logo = db_config('branding.site_logo');
            $primaryColor = db_config('branding.primary_color');

            // Override Laravel app name
            if ($appName) {
                config(['app.name' => $appName]);
            }

            // Override Filament panel branding dynamically
            Filament::serving(function () use ($appName, $logo, $primaryColor) {
                $panel = Filament::getCurrentPanel();

                if (! $panel) {
                    return;
                }

                if ($appName) {
                    $panel->brandName($appName);
                }

                if ($logo) {
                    $logoUrl = str_starts_with($logo, 'http') ? $logo : Storage::url($logo);
                    $panel->brandLogo($logoUrl)->brandLogoHeight('2rem');
                }

                if ($primaryColor) {
                    $color = trim($primaryColor, '"');
                    if ($color) {
                        $panel->colors(['primary' => Color::hex($color)]);
                    }
                }
            });
        } catch (\Exception) {
            return;
        }
    }
}
