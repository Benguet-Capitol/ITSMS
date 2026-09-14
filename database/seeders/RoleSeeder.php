<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Seed the exact 5 roles as they exist in production (snapshot
     * captured 2026-09-03). There is no "Personnel" role in reality —
     * that was a stale placeholder from an earlier scaffold.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            ['id' => 1, 'title' => 'Admin', 'created_at' => '2026-06-09 14:44:10', 'updated_at' => '2026-06-09 14:44:10'],
            ['id' => 2, 'title' => 'It Admin Staff', 'created_at' => '2026-06-09 14:44:10', 'updated_at' => '2026-07-01 09:53:12'],
            ['id' => 3, 'title' => 'It Technical', 'created_at' => '2026-06-09 14:44:10', 'updated_at' => '2026-08-25 16:21:22'],
            ['id' => 4, 'title' => 'Encoder', 'created_at' => '2026-06-09 14:44:10', 'updated_at' => '2026-06-09 14:44:10'],
            ['id' => 5, 'title' => 'User', 'created_at' => '2026-06-09 14:44:10', 'updated_at' => '2026-06-09 14:44:10'],
        ]);
    }
}
