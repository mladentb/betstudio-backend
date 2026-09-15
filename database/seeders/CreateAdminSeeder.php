<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'Admin',
                'password' => Hash::make(env('SEED_ADMIN_PASSWORD', Str::random(32))),
                'role' => 'admin',
                'account_type' => 'seller',
                'phone' => '+381600000000',
                'city' => 'Belgrade',
                'country' => 'Serbia',
                'location' => 'eu',
                'is_active' => true,
                'is_verified' => true,
                'is_company_owner' => true,
                'terms_accepted_at' => now(),
                'approved_at' => now(),
            ]
        );
        
        $this->command->info('Admin user created.');
    }
}
