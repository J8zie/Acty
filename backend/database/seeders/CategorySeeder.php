<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{

    public const NAMES = [
        'Garbage & Waste', 'Flooding', 'Road Damage', 'Street Lights', 'Drainage',
        'Trees & Vegetation', 'Water & Sanitation', 'Public Spaces', 'Public Safety',
    ];


    // Fills the table with starter data.

    public function public function run(): void
    {
        foreach (self::NAMES as $name) {
            Category::query()->firstOrCreate(['name' => $name]);
        }
    }
}
