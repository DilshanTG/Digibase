<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;


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
        // Use try-catch to satisfy older installations or migrations
        try {
            if (! function_exists('db_config')) {
                return;
            }

            // Google OAuth
            $googleId = db_config('auth.google_client_id');
            $googleSecret = db_config('auth.google_client_secret');
            
            if (db_config('auth.google_enabled') && $googleId && $googleSecret) {
                config([
                    'services.google.client_id' => $googleId,
                    'services.google.client_secret' => $googleSecret,
                    'services.google.redirect' => db_config('auth.google_redirect_uri') ?: url('/api/auth/google/callback'),
                ]);
            }

            // GitHub OAuth
            $githubId = db_config('auth.github_client_id');
            $githubSecret = db_config('auth.github_client_secret');

            if (db_config('auth.github_enabled') && $githubId && $githubSecret) {
                config([
                    'services.github.client_id' => $githubId,
                    'services.github.client_secret' => $githubSecret,
                    'services.github.redirect' => db_config('auth.github_redirect_uri') ?: url('/api/auth/github/callback'),
                ]);
            }

        } catch (\Exception $e) {
            // Silently fail if db_config is not ready
        }
    }
}
