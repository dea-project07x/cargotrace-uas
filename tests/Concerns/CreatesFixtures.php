<?php

namespace Tests\Concerns;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait CreatesFixtures
{
    protected function userWithRole(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    protected function actingAsRole(UserRole $role): User
    {
        $user = $this->userWithRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function shipmentFor(User $customer, ShipmentStatus $status = ShipmentStatus::Created): Shipment
    {
        return Shipment::factory()->create([
            'customer_id' => $customer->id,
            'status' => $status,
        ]);
    }

    /** @return array<string, mixed> */
    protected function shipmentPayload(User $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'carrier_id' => Carrier::factory()->create()->id,
            'origin_id' => Location::factory()->create()->id,
            'destination_id' => Location::factory()->create()->id,
            'cargo_type' => 'Electronics',
            'weight_kg' => 1200.5,
            'volume_cbm' => 12,
            'expected_departure_at' => now()->addDay()->toIso8601String(),
            'expected_arrival_at' => now()->addDays(14)->toIso8601String(),
            'items' => [['description' => 'Laptops', 'quantity' => 100, 'unit_weight_kg' => 2.5]],
        ];
    }
}
