<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionRoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin_permissions = Permission::all();

        $personnel_permissions = Permission::whereIn('title', [
          'user_index',
          'user_store',
          'user_update',
          'user_show',
          'user_destroy',
        ])->get();

        $user_permissions = Permission::whereIn('title', [
          'user_index',
        ])->get();

        Role::find(1)->permissions()->attach($admin_permissions); // 1 = System Administrator
        Role::find(2)->permissions()->attach($personnel_permissions); // 2 = Personnel
        Role::find(3)->permissions()->attach($user_permissions); // 3 = User
    }
}
