<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        $arrival = now()->addDays(10)->startOfSecond();

        return [
            'shipment_number' => 'CT-'.fake()->unique()->numerify('########'),
            'customer_id' => User::factory()->state(['role' => UserRole::Customer]),
            'carrier_id' => Carrier::factory(),
            'origin_id' => Location::factory(),
            'destination_id' => Location::factory(),
            'cargo_type' => 'General cargo',
            'weight_kg' => 1000,
            'volume_cbm' => 10,
            'expected_departure_at' => now()->subDays(2)->startOfSecond(),
            'expected_arrival_at' => $arrival,
            'estimated_arrival_at' => $arrival,
            'status' => ShipmentStatus::Created,
            'created_by' => User::factory()->state(['role' => UserRole::Operator]),
        ];
    }
}
