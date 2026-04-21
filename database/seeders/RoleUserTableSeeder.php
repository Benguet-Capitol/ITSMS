<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RoleUserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // User::find('88964338-6721-4816-be39-9a9afbe8df95')->roles()->attach(1); // System Admin - 1 = System Administrator
        User::find('cf6f9ac2-1cd4-43a8-bfba-acdc431282f9')->roles()->attach(2); // KrenjerJS - 2 = Personnel
        User::find('3292e07c-a2a6-40bf-8070-0bb9e76292d6')->roles()->attach(2); // BrianBM - 2 = Personnel
        User::find('5ea07171-e126-4142-9997-9ec24e180e53')->roles()->attach(2); // RaeBC - 2 = Personnel
        User::find('4885e9d6-7f1c-4283-b1c1-954316312be2')->roles()->attach(2); // PerseusP - 2 = Personnel
        User::find('e749da8a-8924-44a5-b9a8-b270f43c0e42')->roles()->attach(2); // NeilsenK - 2 = Personnel
        User::find('bf4475b5-257e-4679-b7bc-35941392d659')->roles()->attach(2); // LesterPM - 2 = Personnel
        // User::find('152f3ecd-2e69-4cd4-bdc8-43c685602e54')->roles()->attach(3); // User - 3 = User
    }
}
