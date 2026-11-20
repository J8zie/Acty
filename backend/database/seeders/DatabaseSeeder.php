<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{


    // Fills the table with starter data.

    public function public function run(): void
    {
        $this->call([CategorySeeder::class, AdminSeeder::class]);
    }
}
