<?php

namespace Database\Factories;

use App\Models\Carrier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Carrier> */
class CarrierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => strtoupper(fake()->unique()->bothify('???##')),
            'contact_email' => fake()->safeEmail(),
            'is_active' => true,
        ];
    }
}
