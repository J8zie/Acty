<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;


    // Makes a fake record for tests.

    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true)];
    }
}
