<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{

    // Fills the table with starter data.

    public function run(): void
    {
        $username = env('ADMIN_USERNAME');
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $username || ! $email || ! $password) {
            return;
        }

        User::query()->firstOrCreate(
            ['username' => mb_strtolower($username)],
            [
                'first_name' => 'Site',
                'last_name' => 'Admin',
                'email' => mb_strtolower($email),
                'password' => $password,
                'role' => Role::Admin,
            ],
        );
    }
}
