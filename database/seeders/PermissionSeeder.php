<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            ['title' => 'user_index'],
            ['title' => 'user_store'],
            ['title' => 'user_update'],
            ['title' => 'user_show'],
            ['title' => 'user_destroy'],

            ['title' => 'role_index'],
            ['title' => 'role_store'],
            ['title' => 'role_update'],
            ['title' => 'role_show'],
            ['title' => 'role_destroy'],

            ['title' => 'permission_index'],
            ['title' => 'permission_store'],
            ['title' => 'permission_update'],
            ['title' => 'permission_show'],
            ['title' => 'permission_destroy'],
        ];

        Permission::insert($permissions);
    }
}
