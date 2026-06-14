<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\School;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create the Arkeon Super Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'suraemu21@gmail.com',
            'password' => Hash::make('EduID@2026!'),
            'role' => 'super_admin',
        ]);

        // 2. Create the Test School (AIA)
        $school = School::create([
            'name' => 'Abyssinia Academy',
            'code' => 'AIA',
            'academic_year' => '2026/2027',
            'calendar_system' => 'ethiopian',
            'hmac_secret' => bin2hex(random_bytes(32)),
        ]);

        // 3. Create the School Admin for AIA
        User::create([
            'school_id' => $school->id,
            'name' => 'School Admin',
            'email' => 'admin@abyssinia.com',
            'password' => Hash::make('password123'),
            'role' => 'school_admin',
        ]);
    }
}