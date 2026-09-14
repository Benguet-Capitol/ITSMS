<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProfileSeeder extends Seeder
{
    /**
     * Exact profiles snapshot from production (2026-09-03).
     */
    public function run(): void
    {
        $name = fn ($first, $middle, $last, $suffix = null) => json_encode([
            'prefix' => null,
            'firstname' => $first,
            'middlename' => $middle,
            'lastname' => $last,
            'suffix' => $suffix,
        ]);

        DB::table('profiles')->insert([
            [
                'id' => 1, 'user_id' => 'cf6f9ac2-1cd4-43a8-bfba-acdc431282f9',
                'display_name' => 'Krenjer Jan Juantala Sapitola',
                'name' => $name('Krenjer Jan', 'Juantala', 'Sapitola'),
                'gender' => 'male', 'designation' => 'ADMINISTRATIVE ASSISTANT V',
                'status' => 'online', 'status_text' => null, 'engagement' => null,
                'img_path' => 'images/users/personnel/1GHRNmnrAwm0D9NvWRtlKHDMGgS5nbsc4trnj6rW.png',
                'last_seen_at' => '2026-09-03 17:32:13',
                'created_at' => '2025-07-29 01:05:55', 'updated_at' => '2026-09-03 17:32:13',
            ],
            [
                'id' => 2, 'user_id' => '3292e07c-a2a6-40bf-8070-0bb9e76292d6',
                'display_name' => 'Brian Basquial Mang-Oy Jr.',
                'name' => $name('Brian', 'Basquial', 'Mang-Oy', ' Jr.'),
                'gender' => 'male', 'designation' => 'COMPUTER MAINTENANCE TECHNOLOGIST II',
                'status' => 'offline', 'status_text' => null, 'engagement' => 'busy',
                'img_path' => 'images/users/personnel/9PHXUYx65bV3EGBftGulQhTC99lKGsXxalRjxA5v.png',
                'last_seen_at' => '2026-09-03 09:48:12',
                'created_at' => '2025-07-29 01:04:01', 'updated_at' => '2026-09-03 10:28:40',
            ],
            [
                'id' => 3, 'user_id' => '5ea07171-e126-4142-9997-9ec24e180e53',
                'display_name' => 'Rae Sandy Benawe Calado',
                'name' => $name('Rae Sandy', 'Benawe', 'Calado'),
                'gender' => 'male', 'designation' => 'COMPUTER MAINTENANCE TECHNOLOGIST I',
                'status' => 'offline', 'status_text' => null, 'engagement' => 'busy',
                'img_path' => 'images/users/personnel/oBsbLa95bj7bVgfjq5vmUy9vjd1xzuMhNjvMnCx9.png',
                'last_seen_at' => '2026-09-03 09:38:27',
                'created_at' => '2025-07-29 01:10:24', 'updated_at' => '2026-09-03 09:51:02',
            ],
            [
                'id' => 4, 'user_id' => '4885e9d6-7f1c-4283-b1c1-954316312be2',
                'display_name' => 'Perseus Burgos Pangaliman',
                'name' => $name('Perseus', 'Burgos', 'Pangaliman'),
                'gender' => 'male', 'designation' => 'COMPUTER MAINTENANCE TECHNOLOGIST I',
                'status' => 'offline', 'status_text' => null, 'engagement' => null,
                'img_path' => 'images/users/personnel/IGMJlm4Ki4O9naHQlhHlDmSRXI9KO0NgTff71cQu.png',
                'last_seen_at' => '2026-09-03 09:12:58',
                'created_at' => '2025-07-29 01:04:14', 'updated_at' => '2026-09-03 09:15:00',
            ],
            [
                'id' => 5, 'user_id' => 'e749da8a-8924-44a5-b9a8-b270f43c0e42',
                'display_name' => 'Neilsen Pasking Kisim',
                'name' => $name('Neilsen', 'Pasking', 'Kisim'),
                'gender' => 'male', 'designation' => 'COMPUTER MAINTENANCE TECHNOLOGIST I',
                'status' => 'offline', 'status_text' => null, 'engagement' => 'busy',
                'img_path' => 'images/users/personnel/O80V47qV1iNkecMfk4xlt4JF3DbzSlyLTcZYoKKb.png',
                'last_seen_at' => '2026-09-03 10:47:10',
                'created_at' => '2025-07-29 01:04:29', 'updated_at' => '2026-09-03 10:56:50',
            ],
            [
                'id' => 6, 'user_id' => 'bf4475b5-257e-4679-b7bc-35941392d659',
                'display_name' => 'Lester Padilla Metua',
                'name' => $name('Lester', 'Padilla', 'Metua'),
                'gender' => 'male', 'designation' => 'COMPUTER MAINTENANCE TECHNOLOGIST I',
                'status' => 'offline', 'status_text' => null, 'engagement' => null,
                'img_path' => 'images/users/personnel/dUU7Jh9YreBhAfrQkHVGIzk89uPbqYdoKcsII04V.png',
                'last_seen_at' => '2026-09-03 13:03:18',
                'created_at' => '2025-07-29 01:04:21', 'updated_at' => '2026-09-03 13:10:00',
            ],
            [
                'id' => 7, 'user_id' => '3686504b-b36c-4fb6-a492-449bb3e9cc5b',
                'display_name' => 'Jenny Rose Tabil Borja',
                'name' => $name('Jenny Rose', 'Tabil', 'Borja'),
                'gender' => 'female', 'designation' => 'INFORMATION SYSTEM ANALYST III',
                'status' => 'offline', 'status_text' => null, 'engagement' => null,
                'img_path' => 'images/users/personnel/zVMGNnzjpaNawCQW4i7M97aluwWvLN0xDKFHCX7T.png',
                'last_seen_at' => '2026-09-02 16:57:37',
                'created_at' => '2025-07-29 01:06:55', 'updated_at' => '2026-09-02 16:57:49',
            ],
            [
                'id' => 8, 'user_id' => '085c6dad-0063-46f3-8a52-29322a7dfeaf',
                'display_name' => 'Chazz Evans Ampaguey Saley',
                'name' => $name('Chazz Evans', 'Ampaguey', 'Saley'),
                'gender' => 'male', 'designation' => 'PROGRAMMER I',
                'status' => 'offline', 'status_text' => null, 'engagement' => null,
                'img_path' => 'images/users/personnel/ppokxcNFSC5yz5fknC93Ss5kRur7HPJGUXlHU1UC.png',
                'last_seen_at' => '2026-06-09 16:16:37',
                'created_at' => '2025-07-29 01:08:12', 'updated_at' => '2026-08-27 15:30:00',
            ],
            [
                'id' => 9, 'user_id' => '4f7f617a-a396-4252-b236-0f6075649560',
                'display_name' => 'Frenie Grale Pocte Wailan',
                'name' => $name('Frenie Grale', 'Pocte', 'Wailan'),
                'gender' => 'female', 'designation' => 'ADMINISTRATIVE AIDE VI',
                'status' => 'online', 'status_text' => null, 'engagement' => null,
                'img_path' => 'images/users/personnel/2MA37e97LA9WjxDy3aQ4qo9aRArRkLfi3hyIFFbe.png',
                'last_seen_at' => '2026-09-03 17:32:10',
                'created_at' => '2025-07-29 01:26:38', 'updated_at' => '2026-09-03 17:32:10',
            ],
            [
                'id' => 10, 'user_id' => 'eaf1da53-f904-4dd4-ba2a-76123dbded9d',
                'display_name' => 'Judy Anne Martinez Angel',
                'name' => $name('Judy Anne', 'Martinez', 'Angel'),
                'gender' => 'female', 'designation' => 'ADMINISTRATIVE AIDE IV',
                'status' => 'offline', 'status_text' => null, 'engagement' => null,
                'img_path' => null,
                'last_seen_at' => '2026-07-01 17:59:32',
                'created_at' => '2025-07-29 01:32:17', 'updated_at' => '2026-08-27 15:30:00',
            ],
            [
                'id' => 11, 'user_id' => '308e637a-239d-4cbc-b0b9-6080ce2cfda9',
                'display_name' => 'Geronimo Ignacio Gemino',
                'name' => $name('Geronimo', 'Ignacio', 'Gemino'),
                'gender' => 'male', 'designation' => 'ADMINISTRATIVE ASSISTANT VI',
                'status' => 'offline', 'status_text' => null, 'engagement' => null,
                'img_path' => 'images/users/personnel/eYHQnEO9NRUYM2vczxMnHrNphcAZTaNZJg9CY3H3.png',
                'last_seen_at' => '2026-08-28 10:33:24',
                'created_at' => '2025-07-29 02:29:20', 'updated_at' => '2026-08-28 10:33:34',
            ],
            [
                'id' => 12, 'user_id' => 'c903c924-1282-42d2-95d5-2b9364530a0c',
                'display_name' => 'Gretchen Joy Bendoza Marrero',
                'name' => $name('Gretchen Joy', 'Bendoza', 'Marrero'),
                'gender' => 'female', 'designation' => 'COMPUTER PROGRAMMER I',
                'status' => null, 'status_text' => null, 'engagement' => 'ready',
                'img_path' => 'images/users/personnel/NTKtLcQJPyw5cSgDiefNCLBDLxprsQnogH7RJgvh.png',
                'last_seen_at' => '2025-07-29 02:30:54',
                'created_at' => '2025-07-29 02:30:54', 'updated_at' => '2026-06-09 14:47:17',
            ],
        ]);
    }
}
