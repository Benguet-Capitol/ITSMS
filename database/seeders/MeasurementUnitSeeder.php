<?php

namespace Database\Seeders;

use App\Models\MeasurementUnit;
use Illuminate\Database\Seeder;

class MeasurementUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $measurement_units = [
          ['name' => 'Set' , 'abbreviation' => 'set', 'description' => 'Grouped items used together'],
          ['name' => 'Piece(s)' , 'abbreviation' => 'pcs', 'description' => 'Countable items'],
          ['name' => 'Unit(s)' , 'abbreviation' => 'unit', 'description' => ''],
        ];

        MeasurementUnit::insert($measurement_units);
    }
}
