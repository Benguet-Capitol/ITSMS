<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the exact production snapshot
     * captured 2026-09-03 (12 users, 5 roles, 95 permissions). Inserted
     * via the query builder rather than User::factory() so the real
     * password hashes, verification timestamps, and remember_tokens are
     * preserved byte-for-byte instead of being overwritten by factory
     * defaults.
     *
     * An earlier fix introduced RolesAndPermissionsSeeder to reconcile a
     * dual-seeder bug; that class has since been removed entirely — the
     * exact production snapshot below made it redundant, and keeping it
     * around risked a curated permission set silently overwriting real
     * data on any role title that matched case-insensitively.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            ['id' => '085c6dad-0063-46f3-8a52-29322a7dfeaf', 'username' => 'ChazzAS', 'email' => 'chazzas@itsms.com', 'email_verified_at' => null, 'password' => '$2y$12$DUZNEqFccVrmXGahAuMn5OV4depiNCRno6Yy5btKlfVKSPSdrBcam', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => null, 'created_at' => '2025-07-29 01:08:12', 'updated_at' => '2025-07-29 01:08:12'],
            ['id' => '308e637a-239d-4cbc-b0b9-6080ce2cfda9', 'username' => 'GeronimoIG', 'email' => 'geronimoig@itsms.com', 'email_verified_at' => null, 'password' => '$2y$12$9mux9XAGJtUccqrnmHYVsOyq.9sk5Imk0XnSKaZe2BARAiHTikAGe', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => null, 'created_at' => '2025-07-29 02:29:20', 'updated_at' => '2025-07-29 02:29:20'],
            ['id' => '3292e07c-a2a6-40bf-8070-0bb9e76292d6', 'username' => 'BrianBM', 'email' => 'brianbm@itsms.com', 'email_verified_at' => '2025-07-28 07:40:13', 'password' => '$2y$12$zS1Bsba8lrafDDSei1kqTOWED.JhHgw1/U8q0XqNYaWR/7.7wEda2', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => '23lTz9dX6iCmOC1nzyXdJ4BznvFED19H7JAT4UAwEjpNVKDCb8E6dwgqbDzF', 'created_at' => '2025-07-28 07:40:13', 'updated_at' => '2025-07-28 07:40:13'],
            ['id' => '3686504b-b36c-4fb6-a492-449bb3e9cc5b', 'username' => 'JennyTB', 'email' => 'jennytb@itsms.com', 'email_verified_at' => null, 'password' => '$2y$12$Z9K40ffG48gXzQrpTtnAz.F5rj6LO3NVyznPABN2k2MzZ1JMXxxae', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => null, 'created_at' => '2025-07-29 01:06:55', 'updated_at' => '2025-07-29 01:06:55'],
            ['id' => '4885e9d6-7f1c-4283-b1c1-954316312be2', 'username' => 'PerseusBP', 'email' => 'perseusbp@itsms.com', 'email_verified_at' => '2025-07-28 07:40:13', 'password' => '$2y$12$zS1Bsba8lrafDDSei1kqTOWED.JhHgw1/U8q0XqNYaWR/7.7wEda2', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => 'LFZjsbUT4l', 'created_at' => '2025-07-28 07:40:13', 'updated_at' => '2025-07-28 07:40:13'],
            ['id' => '4f7f617a-a396-4252-b236-0f6075649560', 'username' => 'FreniePW', 'email' => 'freniepw@itsms.com', 'email_verified_at' => null, 'password' => '$2y$12$cEO5oR3EFLJxoYFaEZmTOOUi2IHssv6D8t3w3SlheqGi0SF/8HfHK', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => null, 'created_at' => '2025-07-29 01:26:38', 'updated_at' => '2025-07-29 01:26:38'],
            ['id' => '5ea07171-e126-4142-9997-9ec24e180e53', 'username' => 'RaeBC', 'email' => 'raebc@itsms.com', 'email_verified_at' => '2025-07-28 07:40:13', 'password' => '$2y$12$zS1Bsba8lrafDDSei1kqTOWED.JhHgw1/U8q0XqNYaWR/7.7wEda2', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => 'LQBNWabXmVQHFcpcIuEZChvyTShGSwVSEdmXEGjeWfDRfeR364e8BUbAQgf2', 'created_at' => '2025-07-28 07:40:13', 'updated_at' => '2025-07-28 07:40:13'],
            ['id' => 'bf4475b5-257e-4679-b7bc-35941392d659', 'username' => 'LesterPM', 'email' => 'lesterpm@itsms.com', 'email_verified_at' => '2025-07-28 07:40:13', 'password' => '$2y$12$zS1Bsba8lrafDDSei1kqTOWED.JhHgw1/U8q0XqNYaWR/7.7wEda2', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => 'aoDYeksYJsXxoZcNMKE0huI4qCZDhccgkNjmE678EEtptkCGsNWCgiT5Pbqr', 'created_at' => '2025-07-28 07:40:13', 'updated_at' => '2025-07-28 07:40:13'],
            ['id' => 'c903c924-1282-42d2-95d5-2b9364530a0c', 'username' => 'GretchenBM', 'email' => 'gretchenbm@itsms.com', 'email_verified_at' => null, 'password' => '$2y$12$xmNxggiG4KexRHMBFBg/w.ab1vzCq4mic0pAQbQDYjNUUpwt2NgdS', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => null, 'created_at' => '2025-07-29 02:30:54', 'updated_at' => '2025-07-29 02:30:54'],
            ['id' => 'cf6f9ac2-1cd4-43a8-bfba-acdc431282f9', 'username' => 'KrenjerJS', 'email' => 'krenjerjs@itsms.com', 'email_verified_at' => '2025-07-28 07:40:12', 'password' => '$2y$12$zS1Bsba8lrafDDSei1kqTOWED.JhHgw1/U8q0XqNYaWR/7.7wEda2', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => 'oVJJCcawaAXgZR6gRIyww7ldOTA4iFWNupb3VQbAyvUsJyfEpUbi2sKf8hTq', 'created_at' => '2025-07-28 07:40:13', 'updated_at' => '2025-07-28 07:40:13'],
            ['id' => 'e749da8a-8924-44a5-b9a8-b270f43c0e42', 'username' => 'NeilsenPK', 'email' => 'neilsenpk@itsms.com', 'email_verified_at' => '2025-07-28 07:40:13', 'password' => '$2y$12$zS1Bsba8lrafDDSei1kqTOWED.JhHgw1/U8q0XqNYaWR/7.7wEda2', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => 'ooCTHuFX74zv0iLofO7OsDW1NCSKdaRvvUe3VkSMkRppuGJEKJa0hNZfQskz', 'created_at' => '2025-07-28 07:40:13', 'updated_at' => '2025-07-28 07:40:13'],
            ['id' => 'eaf1da53-f904-4dd4-ba2a-76123dbded9d', 'username' => 'JudyMA', 'email' => 'judyma@itsms.com', 'email_verified_at' => null, 'password' => '$2y$12$KC2RNNTqKrDjyv90T9GW3O.g1.i5TSeaIvt1WCmQm78bt/JZ0wi6i', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => null, 'created_at' => '2025-07-29 01:31:09', 'updated_at' => '2025-07-29 01:31:09'],
        ]);

        $this->call([
            ProfileSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            PermissionRoleTableSeeder::class,
            RoleUserTableSeeder::class,
            MeasurementUnitSeeder::class,
        ]);
    }
}
