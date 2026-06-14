<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email'     => 'suraemu21@gmail.com',
                'school_id' => null,
            ],
            [
                'name'      => 'Super Admin',
                'password'  => Hash::make('EduID@2026!'),
                'role'      => 'super_admin',
                'is_active' => true,
            ]
        );

        $this->command->info('✓ Super Admin seeded: suraemu21@gmail.com');
        $this->command->warn('  Default password: EduID@2026! — change this immediately after first login.');
    }
}
