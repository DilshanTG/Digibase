<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SocialConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     * 
     * Override Laravel's services config with database settings.
     * This allows admins to configure OAuth without touching .env files.
     */
    public function boot(): void
    {
        // CRITICAL: Wrap in try-catch to prevent crashes during migration
        // or when database is not yet set up
        try {
            // Check if settings table exists first
            if (!Schema::hasTable('settings')) {
                return;
            }

            // Fetch all social auth settings in one query
            $settings = DB::table('settings')
                ->where('group', 'authentication')
                ->pluck('value', 'key')
                ->toArray();

            // Parse JSON values if needed
            $getValue = function ($key) use ($settings) {
                $value = $settings[$key] ?? null;
                if ($value && is_string($value)) {
                    $decoded = json_decode($value, true);
                    return $decoded ?? $value;
                }
                return $value;
            };

            // Override Google OAuth config
            if ($getValue('google_active')) {
                config([
                    'services.google.client_id' => $getValue('google_client_id'),
                    'services.google.client_secret' => $getValue('google_client_secret'),
                    'services.google.redirect' => $getValue('google_redirect_uri') ?: url('/api/auth/google/callback'),
                ]);
            }

            // Override GitHub OAuth config
            if ($getValue('github_active')) {
                config([
                    'services.github.client_id' => $getValue('github_client_id'),
                    'services.github.client_secret' => $getValue('github_client_secret'),
                    'services.github.redirect' => $getValue('github_redirect_uri') ?: url('/api/auth/github/callback'),
                ]);
            }

        } catch (\Exception $e) {
            // Silently fail - database might not be ready yet
            // This happens during migrations or fresh installs
            report($e);
        }
    }
}
