<?php

namespace Database\Seeders;

use App\Models\Benefit;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::firstOrCreate([
            'name' => 'مؤسسة تجريبية',
        ]);

        User::updateOrCreate([
            'email' => 'developer@example.com',
        ], [
            'name' => 'المطور',
            'password' => 'password123',
            'role' => User::ROLE_DEVELOPER,
        ]);

        $admin = User::updateOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'مسؤول المؤسسة',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'institution_id' => $institution->id,
        ]);

        User::updateOrCreate([
            'email' => 'entry@example.com',
        ], [
            'name' => 'مدخل بيانات',
            'password' => 'password123',
            'role' => User::ROLE_DATA_ENTRY,
            'institution_id' => $institution->id,
            'created_by' => $admin->id,
        ]);

        foreach (['مساعدة غذائية', 'دعم صحي', 'منحة تعليمية'] as $benefitName) {
            Benefit::firstOrCreate(['name' => $benefitName]);
        }

    }
}
