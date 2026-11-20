<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;


    // Makes a fake record for tests.

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName().fake()->unique()->numerify('##'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'pass1234',
            'role' => Role::Resident,
        ];
    }

    public function resident(): static
    {
        return $this->state(['role' => Role::Resident]);
    }

    public function volunteer(): static
    {
        return $this->state(['role' => Role::Volunteer]);
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }
}
