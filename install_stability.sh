#!/bin/bash

# 0. Create necessary directories & permissions
mkdir -p storage/app/public/livewire-tmp
chmod 777 storage/app/public/livewire-tmp

# 1. Update packages (ignoring exif requirement if missing on dev)
composer update spatie/laravel-activitylog spatie/laravel-settings spatie/laravel-backup --with-all-dependencies --ignore-platform-req=ext-exif

# 2. Publish Activity Log assets
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"

# 3. Publish Settings assets
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="migrations"
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="config"

# 4. Storage Link
php artisan storage:link

# 5. Run migrations
php artisan migrate

# 6. Clear caches to ensure new settings and config are loaded
php artisan optimize:clear
php artisan config:clear
