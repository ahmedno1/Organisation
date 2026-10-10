<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Seed institutions
        DB::table('institutions')->insert([
            ['name' => 'Institution A'],
            ['name' => 'Institution B'],
        ]);

        // Seed administrators
        DB::table('administrators')->insert([
            ['name' => 'Admin A', 'email' => 'adminA@example.com', 'password' => bcrypt('password')],
            ['name' => 'Admin B', 'email' => 'adminB@example.com', 'password' => bcrypt('password')],
        ]);

        // Seed data entry personnel
        DB::table('data_entries')->insert([
            ['administrator_id' => 1, 'name' => 'Data Entry A'],
            ['administrator_id' => 1, 'name' => 'Data Entry B'],
            ['administrator_id' => 2, 'name' => 'Data Entry C'],
        ]);

        // Seed families
        DB::table('families')->insert([
            ['head_of_family' => 'John Doe', 'administrator_id' => 1],
            ['head_of_family' => 'Jane Smith', 'administrator_id' => 2],
        ]);

        // Seed benefits
        DB::table('benefits')->insert([
            ['family_id' => 1, 'description' => 'Benefit A'],
            ['family_id' => 2, 'description' => 'Benefit B'],
        ]);

        // Seed records
        DB::table('records')->insert([
            ['family_id' => 1, 'data' => 'Record A1'],
            ['family_id' => 1, 'data' => 'Record A2'],
            ['family_id' => 2, 'data' => 'Record B1'],
        ]);
    }
}