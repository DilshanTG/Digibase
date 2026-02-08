<?php

namespace App\Providers;

use App\Models\Setting;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureBranding();
    }

    private function configureBranding(): void
    {
        // Guard: skip if DB isn't ready (migrations haven't run yet)
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
        } catch (\Exception) {
            return;
        }

        $settings = Setting::where('group', 'branding')
            ->pluck('value', 'key')
            ->toArray();

        if (empty($settings)) {
            return;
        }

        // Override Laravel app name
        if (! empty($settings['app_name'])) {
            config(['app.name' => $settings['app_name']]);
        }

        // Override Filament panel branding dynamically
        Filament::serving(function () use ($settings) {
            $panel = Filament::getCurrentPanel();

            if (! $panel) {
                return;
            }

            if (! empty($settings['app_name'])) {
                $name = is_string($settings['app_name']) ? $settings['app_name'] : (string) $settings['app_name'];
                $panel->brandName(trim($name, '"'));
            }

            if (! empty($settings['logo_url'])) {
                $logo = $settings['logo_url'];
                if ($logo) {
                    $logoUrl = str_starts_with($logo, 'http') ? $logo : Storage::url($logo);
                    $panel->brandLogo($logoUrl)->brandLogoHeight('2rem');
                }
            }

            if (! empty($settings['primary_color'])) {
                $color = is_string($settings['primary_color']) ? $settings['primary_color'] : (string) $settings['primary_color'];
                $color = trim($color, '"');
                if ($color) {
                    $panel->colors(['primary' => Color::hex($color)]);
                }
            }
        });
    }
}
