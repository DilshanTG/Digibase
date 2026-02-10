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
            
            if ($driver === 's3') {
                config([
                    'filesystems.default' => 's3',
                    'filesystems.disks.s3.key' => \App\Models\SystemSetting::get('aws_access_key_id'),
                    'filesystems.disks.s3.secret' => \App\Models\SystemSetting::get('aws_secret_access_key'),
                    'filesystems.disks.s3.region' => \App\Models\SystemSetting::get('aws_default_region', 'us-east-1'),
                    'filesystems.disks.s3.bucket' => \App\Models\SystemSetting::get('aws_bucket'),
                    'filesystems.disks.s3.endpoint' => \App\Models\SystemSetting::get('aws_endpoint'),
                    'filesystems.disks.s3.use_path_style_endpoint' => \App\Models\SystemSetting::get('aws_use_path_style') === 'true',
                ]);
            }
        } catch (\Exception $e) {
            // Silence is golden during early boot/migrations
        }
    }
}
