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
        $this->configureStorage();
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

    /**
     * ☁️ UNIVERSAL STORAGE ADAPTER: Dynamic filesystem configuration.
     * Loads settings from DB and configures the 'digibase_storage' disk.
     */
    private function configureStorage(): void
    {
        try {
            if (!class_exists(\App\Models\SystemSetting::class)) return;

            $driver = \App\Models\SystemSetting::get('storage.driver', 'local');
            $diskConfig = [];

            if ($driver === 'local') {
                $diskConfig = [
                    'driver' => 'local',
                    'root' => storage_path('app/public'),
                    'url' => env('APP_URL').'/storage',
                    'visibility' => 'public',
                    'throw' => false,
                ];
            } else {
                // S3 Compatible (AWS, R2, Spaces, MinIO)
                $diskConfig = [
                    'driver' => 's3',
                    'key' => \App\Models\SystemSetting::get('storage.access_key'),
                    'secret' => \App\Models\SystemSetting::get('storage.secret_key'),
                    'region' => \App\Models\SystemSetting::get('storage.region', 'us-east-1'),
                    'bucket' => \App\Models\SystemSetting::get('storage.bucket'),
                    'endpoint' => \App\Models\SystemSetting::get('storage.endpoint'),
                    'use_path_style_endpoint' => \App\Models\SystemSetting::get('storage.use_path_style') === 'true',
                    'url' => \App\Models\SystemSetting::get('storage.public_url'),
                    'visibility' => 'public', // Default to public for now, or control per file
                    'throw' => false,
                ];
            }

            // Register the dynamic disk
            config(['filesystems.disks.digibase_storage' => $diskConfig]);
            
            // Set as default if needed, or just ensure our controllers use it
            // config(['filesystems.default' => 'digibase_storage']); 

        } catch (\Exception $e) {
            // Fallback to local if DB fails
        }
    }
}
