<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Model permissions
            'view models',
            'create models',
            'edit models',
            'delete models',
            // API permissions
            'view api',
            'manage api',
            // Database permissions
            'view database',
            'manage database',
            // User permissions
            'view users',
            'create users',
            'edit users',
            'delete users',
            // Settings permissions
            'view settings',
            'manage settings',
            // Admin permissions
            'access admin panel',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        $userRole = Role::create(['name' => 'user']);
        $userRole->givePermissionTo([
            'view models',
            'create models',
            'edit models',
            'delete models',
            'view api',
            'view database',
            'view settings',
        ]);
    }
}
