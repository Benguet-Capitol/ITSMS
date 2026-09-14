<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleUserTableSeeder extends Seeder
{
    /**
     * Exact role_user pivot snapshot from production (2026-09-03).
     * Note: JudyMA (eaf1da53-f904-4dd4-ba2a-76123dbded9d) has NO role
     * assignment in production — preserved as-is, not invented.
     */
    public function run(): void
    {
        DB::table('role_user')->insert([
            ['role_id' => 1, 'user_id' => 'cf6f9ac2-1cd4-43a8-bfba-acdc431282f9'], // KrenjerJS -> Admin
            ['role_id' => 3, 'user_id' => 'e749da8a-8924-44a5-b9a8-b270f43c0e42'], // NeilsenPK -> It Technical
            ['role_id' => 3, 'user_id' => '3292e07c-a2a6-40bf-8070-0bb9e76292d6'], // BrianBM -> It Technical
            ['role_id' => 3, 'user_id' => '4885e9d6-7f1c-4283-b1c1-954316312be2'], // PerseusBP -> It Technical
            ['role_id' => 3, 'user_id' => '5ea07171-e126-4142-9997-9ec24e180e53'], // RaeBC -> It Technical
            ['role_id' => 3, 'user_id' => 'bf4475b5-257e-4679-b7bc-35941392d659'], // LesterPM -> It Technical
            ['role_id' => 3, 'user_id' => '085c6dad-0063-46f3-8a52-29322a7dfeaf'], // ChazzAS -> It Technical
            ['role_id' => 2, 'user_id' => '4f7f617a-a396-4252-b236-0f6075649560'], // FreniePW -> It Admin Staff
            ['role_id' => 3, 'user_id' => '308e637a-239d-4cbc-b0b9-6080ce2cfda9'], // GeronimoIG -> It Technical
            ['role_id' => 3, 'user_id' => 'c903c924-1282-42d2-95d5-2b9364530a0c'], // GretchenBM -> It Technical
            ['role_id' => 1, 'user_id' => '3686504b-b36c-4fb6-a492-449bb3e9cc5b'], // JennyTB -> Admin
        ]);
    }
}
