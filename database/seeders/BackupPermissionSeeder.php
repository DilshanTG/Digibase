<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BackupPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure Spatie permissions are loaded
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create the permission if it doesn't exist
        $permissions = [
            'create-backup',
            'download-backup',
            'delete-backup',
        ];

        foreach ($permissions as $permName) {
            $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permName]);

            // Find the admin user (ID 1) or 'admin' role
            $user = \App\Models\User::find(1);
            if ($user) {
                $user->givePermissionTo($permission);
            }

            // Ideally assign to Admin role too
            $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
            $role->givePermissionTo($permission);
        }
    }
}
