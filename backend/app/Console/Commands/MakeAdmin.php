<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

// Makes an existing account an admin from the command line.
class MakeAdmin extends Command
{
    protected $signature = 'actify:make-admin {username : the username of an account that already exists}';

    protected $description = 'Give an existing account the admin role';

    // Finds the user and gives them the admin role.
    public function handle(): int
    {
        $user = User::query()->where('username', mb_strtolower($this->argument('username')))->first();

        if (! $user) {
            $this->error('No account with that username. Register it on the website first, then run this again.');

            return self::FAILURE;
        }

        $user->role = Role::Admin;
        $user->save();

        $this->info("{$user->username} is now an admin.");

        return self::SUCCESS;
    }
}
