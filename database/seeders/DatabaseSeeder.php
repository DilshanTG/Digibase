<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RoleSeeder::class);

        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@digibase.dev',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        // Create test user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $user->assignRole('user');

        // Seed Default Settings (FilamentDbConfig)
        if (function_exists('db_config')) {
            // Branding Defaults
            // Use ?? operator or check for null if you want to avoid overwriting existing
            // But here we set initial defaults.
            if (db_config('branding.site_name') === null) db_config('branding.site_name', 'Digibase');
            if (db_config('branding.site_logo') === null) db_config('branding.site_logo', null);

            // Auth Defaults (Placeholders)
            if (db_config('auth.google_enabled') === null) db_config('auth.google_enabled', false);
            if (db_config('auth.github_enabled') === null) db_config('auth.github_enabled', false);

            if (db_config('auth.google_client_id') === null) db_config('auth.google_client_id', '');
            if (db_config('auth.google_client_secret') === null) db_config('auth.google_client_secret', '');
            if (db_config('auth.github_client_id') === null) db_config('auth.github_client_id', '');
            if (db_config('auth.github_client_secret') === null) db_config('auth.github_client_secret', '');
        }
    }
}
