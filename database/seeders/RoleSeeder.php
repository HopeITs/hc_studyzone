<?php

namespace Database\Seeders;


use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        // Create Roles
        $generalAdmin = Role::create(['name' => 'general_admin', 'guard_name' => 'web']);
        $cityAdmin = Role::create(['name' => 'city_admin' , 'guard_name' => 'web']);
        $centerAdmin = Role::create(['name' => 'center_admin' , 'guard_name' => 'web']);

        // Define Permissions
        Permission::create(['name' => 'manage_admins']);
        Permission::create(['name' => 'manage_city_admins']);
        Permission::create(['name' => 'manage_center_admins']);
        Permission::create(['name' => 'manage_students']);
        Permission::create(['name' => 'view_statistics']);
        Permission::create(['name' => 'view_city_data']);
        Permission::create(['name' => 'view_center_data']);

        // Assign Permissions to Roles
        $generalAdmin->givePermissionTo(['manage_admins', 'view_statistics' ,'manage_city_admins','manage_center_admins', 'manage_students','view_city_data','view_center_data']);
        $cityAdmin->givePermissionTo(['manage_center_admins', 'view_city_data' , 'manage_students']);
        $centerAdmin->givePermissionTo(['manage_students', 'view_center_data']);
    }
}
