<?php

namespace Database\Seeders;

use App\Models\Profile;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $profiles = [
          // Administrator
          // ['user_id' => '88964338-6721-4816-be39-9a9afbe8df95', 'display_name' => 'System Administrator', 'designation' => 'System Administrator', 'gender' => 'other', 'name' => json_encode(['prefix' => null, 'firstname' => 'System', 'middlename' => null, 'lastname' => 'Admin', 'suffix' => null]), 'engagement' => 'ready', 'img_path' => null],
          // Personnel
          ['user_id' => 'cf6f9ac2-1cd4-43a8-bfba-acdc431282f9', 'display_name' => 'Krenjer Jan J. Sapitola', 'designation' => 'Computer Programmer I' , 'gender' => 'male', 'name' => json_encode(['prefix' => null, 'firstname' => 'Krenjer Jan', 'middlename' => 'Juantala', 'lastname' => 'Sapitola', 'suffix' => null]), 'engagement' => 'ready', 'img_path' => null],
          ['user_id' => '3292e07c-a2a6-40bf-8070-0bb9e76292d6', 'display_name' => 'Brian B. Mang-Oy Jr.', 'designation' => 'CMT II' , 'gender' => 'male', 'name' => json_encode(['prefix' => null, 'firstname' => 'Brian', 'middlename' => 'Basquial', 'lastname' => 'Mang-Oy', 'suffix' => 'Jr.']), 'engagement' => 'ready', 'img_path' => null],
          ['user_id' => '5ea07171-e126-4142-9997-9ec24e180e53', 'display_name' => 'Rae Sandy B. Calado', 'designation' => 'CMT I' , 'gender' => 'female', 'name' => json_encode(['prefix' => null, 'firstname' => 'Rae Sandy', 'middlename' => 'B', 'lastname' => 'Calado', 'suffix' => null]), 'engagement' => 'ready', 'img_path' => null],
          ['user_id' => '4885e9d6-7f1c-4283-b1c1-954316312be2', 'display_name' => 'Perseus B. Pangilinan', 'designation' => 'CMT I' , 'gender' => 'male', 'name' => json_encode(['prefix' => null, 'firstname' => 'Perseus', 'middlename' => 'Bugtong', 'lastname' => 'Pangilinan', 'suffix' => null]), 'engagement' => 'ready', 'img_path' => null],
          ['user_id' => 'e749da8a-8924-44a5-b9a8-b270f43c0e42', 'display_name' => 'Neilsen P. Kisim', 'designation' => 'CMT I' , 'gender' => 'male', 'name' => json_encode(['prefix' => null, 'firstname' => 'Neilsen', 'middlename' => 'Pasking', 'lastname' => 'Kisim', 'suffix' => null]), 'engagement' => 'ready', 'img_path' => null],
          ['user_id' => 'bf4475b5-257e-4679-b7bc-35941392d659', 'display_name' => 'Lester P. Metua', 'designation' => 'CMT I' , 'gender' => 'male', 'name' => json_encode(['prefix' => null, 'firstname' => 'Lester', 'middlename' => 'Padilla', 'lastname' => 'Metua', 'suffix' => null]), 'engagement' => 'ready', 'img_path' => null],
          // User
          // ['user_id' => '152f3ecd-2e69-4cd4-bdc8-43c685602e54', 'display_name' => 'User', 'designation' => 'User' , 'engagement' => 'ready'],
        ];

        Profile::insert($profiles);
    }
}
