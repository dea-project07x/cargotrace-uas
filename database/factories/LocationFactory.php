<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Port',
            'code' => strtoupper(fake()->unique()->bothify('??###')),
            'city' => fake()->city(),
            'country_code' => 'ID',
            'latitude' => fake()->latitude(-10, 5),
            'longitude' => fake()->longitude(95, 140),
        ];
    }
}
