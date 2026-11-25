<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        User::factory()->create([
            'name' => 'Demo User',
            'email' => 'user@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        User::factory()->count(28)->create();
    }
}
