<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Create admin user
$user = User::updateOrCreate(
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
        'terms_accepted_at' => now(),
        'approved_at' => now(),
    ]
);

echo "Admin user created: {$user->email}\n";
