<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Permission::create(['name' => 'Create-Role', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Read-Roles', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Update-Role', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Delete-Role', 'guard_name' => 'sanctum']);

        Permission::create(['name' => 'Create-Permission', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Read-Permissions', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Update-Permission', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Delete-Permission', 'guard_name' => 'sanctum']);

        Permission::create(['name' => 'Create-Admin', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Read-Admins', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Update-Admin', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Delete-Admin', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Blocked-Admin', 'guard_name' => 'sanctum']);
    }
}
