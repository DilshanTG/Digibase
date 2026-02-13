<?php

namespace App\Providers;

use App\Models\DynamicRecord;
use App\Observers\DynamicRecordObserver;
use App\Settings\GeneralSettings;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 🛡️ Iron Dome: Ensure API always returns JSON on errors
        if (request()->is('api/*')) {
            request()->headers->set('Accept', 'application/json');
        }

        // 🧠 CENTRAL NERVOUS SYSTEM: Register the Observer
        DynamicRecord::observe(DynamicRecordObserver::class);

        // 👻 ORPHAN CLEANUP: Register DynamicModel Observer
        \App\Models\DynamicModel::observe(\App\Observers\DynamicModelObserver::class);

        // 🔒 SECURITY: Log Viewer Access Control
        $this->configureLogViewerSecurity();

        // 🩺 MONITORING: Pulse Dashboard Access Control
        $this->configurePulseSecurity();

        // 🩺 HEALTH: System Health Checks
        $this->configureHealthChecks();

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

    /**
     * 🩺 HEALTH CHECKS: Register system health monitoring.
     * Dashboard available via Filament Health plugin.
     */
    private function configureHealthChecks(): void
    {
        $isProduction = app()->environment('production', 'staging');

        $checks = [
            DebugModeCheck::new()
                ->expectedToBe(! $isProduction),
            EnvironmentCheck::new()
                ->expectEnvironment(app()->environment()),
            DatabaseCheck::new(),
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(70)
                ->failWhenUsedSpaceIsAbovePercentage(90),
        ];

        // Optimized App only matters in production (caching configs/routes)
        if ($isProduction) {
            $checks[] = OptimizedAppCheck::new();
        }

        Health::checks($checks);
    }

    private function configureBranding(): void
    {
        try {
            if (! function_exists('db_config')) {
                return;
            }

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
            // Early return if settings table doesn't exist yet
            if (! Schema::hasTable('spatie_settings')) {
                return;
            }

            $settings = app(GeneralSettings::class);

            // 🚀 MIGRATION: Migrate legacy SystemSettings if needed
            try {
                if (Schema::hasTable('system_settings') && \Illuminate\Support\Facades\DB::table('system_settings')->count() > 0 && \Illuminate\Support\Facades\DB::table('spatie_settings')->where('group', 'general')->doesntExist()) {
                    $legacy = \Illuminate\Support\Facades\DB::table('system_settings')->pluck('value', 'key');

                    $settings->storage_driver = $legacy['storage_driver'] ?? 'local';
                    $settings->aws_access_key_id = $legacy['aws_access_key_id'] ?? null;

                    try {
                        $settings->aws_secret_access_key = isset($legacy['aws_secret_access_key'])
                            ? Crypt::decryptString($legacy['aws_secret_access_key'])
                            : null;
                    } catch (\Exception $e) {
                        // If decryption fails, likely stored as plain text or invalid. Keep raw or null.
                        // Assuming raw if decryption fails is risky but better than crash.
                        // Actually, if it fails, let's leave it null or try raw?
                        // Let's fallback to null safely to allow admin to reset it.
                        \Illuminate\Support\Facades\Log::warning('Failed to decrypt aws_secret_access_key during migration: '.$e->getMessage());
                        $settings->aws_secret_access_key = null;
                    }

                    $settings->aws_default_region = $legacy['aws_default_region'] ?? 'us-east-1';
                    $settings->aws_bucket = $legacy['aws_bucket'] ?? null;
                    $settings->aws_endpoint = $legacy['aws_endpoint'] ?? null;
                    $settings->aws_use_path_style = $legacy['aws_use_path_style'] ?? 'false';
                    $settings->aws_url = $legacy['aws_url'] ?? null;

                    $settings->save();

                    // Drop the old table to complete the migration
                    Schema::dropIfExists('system_settings');
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Settings migration failed: '.$e->getMessage());
            }

            $driver = $settings->storage_driver ?? 'local';

            // Build the configuration for our dynamic 'digibase_storage' disk
            $storageConfig = [
                'driver' => $driver,
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ];

            if ($driver === 's3') {
                $storageConfig = array_merge($storageConfig, [
                    'key' => $settings->aws_access_key_id,
                    'secret' => $settings->aws_secret_access_key,
                    'region' => $settings->aws_default_region,
                    'bucket' => $settings->aws_bucket,
                    'endpoint' => $settings->aws_endpoint,
                    'use_path_style_endpoint' => $settings->aws_use_path_style === 'true',
                    'url' => $settings->aws_url,
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

            // 🚀 FORCE Livewire to use our storage disk for temp uploads to ensure visibility & S3 compatibility
            config(['livewire.temporary_file_upload.disk' => 'digibase_storage']);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to configure storage: '.$e->getMessage());
        }
    }
}
