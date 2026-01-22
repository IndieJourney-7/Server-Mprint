<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user if doesn't exist
        User::updateOrCreate(
            ['email' => 'admin@mprint.com'],
            [
                'name' => 'Admin',
                'email' => 'admin@mprint.com',
                'password' => Hash::make('mprint@321'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Admin user created/updated successfully!');
        $this->command->info('Email: admin@mprint.com');
        $this->command->info('Password: mprint@321');
    }
}
