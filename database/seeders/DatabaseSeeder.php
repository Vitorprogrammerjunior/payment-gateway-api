<?php

namespace Database\Seeders;

use App\Models\Gateway;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::factory()->create([
            'name'     => 'Admin',
            'email'    => 'admin@betalent.tech',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        // Gateways (priority 1 = first tried, 2 = fallback)
        Gateway::create(['name' => 'gateway1', 'is_active' => true, 'priority' => 1]);
        Gateway::create(['name' => 'gateway2', 'is_active' => true, 'priority' => 2]);
    }
}
