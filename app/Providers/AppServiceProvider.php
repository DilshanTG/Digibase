<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\DynamicRecord;
use App\Observers\DynamicRecordObserver;
use Laravel\Pulse\Facades\Pulse;

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

        // 🔒 SECURITY: Log Viewer Access Control
        $this->configureLogViewerSecurity();

        // 🩺 MONITORING: Pulse Dashboard Access Control
        $this->configurePulseSecurity();

        Scramble::extendOpenApi(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer')
            );
        });

        $this->configureBranding();
        $this->configureStorage();
    }

    /**
     * 🔒 SECURITY: Restrict Log Viewer to Admins Only
     * Only User ID 1 or users with is_admin flag can access logs.
     */
    private function configureLogViewerSecurity(): void
    {
        Gate::define('viewLogViewer', function ($user) {
            // Allow User ID 1 (super admin) or users with is_admin flag
            return $user->id === 1 || ($user->is_admin ?? false);
        });
    }

    private function configurePulseSecurity(): void
    {
        Gate::define('viewPulse', function ($user) {
            return $user->id === 1;
        });
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

            $driver = \App\Models\SystemSetting::get('storage_driver', 'local');
            
            // Build the configuration for our dynamic 'digibase_storage' disk
            $storageConfig = [
                'driver' => $driver,
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ];

            if ($driver === 's3') {
                $storageConfig = array_merge($storageConfig, [
                    'key' => \App\Models\SystemSetting::get('aws_access_key_id'),
                    'secret' => \App\Models\SystemSetting::get('aws_secret_access_key'),
                    'region' => \App\Models\SystemSetting::get('aws_default_region', 'us-east-1'),
                    'bucket' => \App\Models\SystemSetting::get('aws_bucket'),
                    'endpoint' => \App\Models\SystemSetting::get('aws_endpoint'),
                    'use_path_style_endpoint' => \App\Models\SystemSetting::get('aws_use_path_style') === 'true',
                    'url' => \App\Models\SystemSetting::get('aws_url'),
                ]);

                // Also update default s3 and default disk for global Laravel operations
                config(['filesystems.default' => 's3']);
                config(['filesystems.disks.s3' => array_merge(config('filesystems.disks.s3', []), $storageConfig)]);
            } else {
                // Local configuration
                $storageConfig = array_merge($storageConfig, [
                    'root' => storage_path('app/public'),
                    'url' => rtrim(config('app.url', 'http://localhost'), '/').'/storage',
                ]);
            }

            // 🚀 Register the critical 'digibase_storage' disk used by the Core Engine
            config(['filesystems.disks.digibase_storage' => $storageConfig]);

        } catch (\Exception $e) {
            // Silence is golden during early boot/migrations
        }
    }
}
