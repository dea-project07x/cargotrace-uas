<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\TrackingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFixtures;
use Tests\TestCase;

class WebShipmentMapTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function event($shipment, array $attributes): TrackingEvent
    {
        return TrackingEvent::create($attributes + [
            'shipment_id' => $shipment->id,
            'event_type' => 'UPDATE',
            'is_exception' => false,
            'occurred_at' => now()->subHour(),
            'created_by' => $shipment->created_by,
        ]);
    }

    public function test_detail_page_contains_the_map_container(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $shipment = $this->shipmentFor($customer, ShipmentStatus::InTransit);

        $this->actingAs($customer)->get("/shipments/{$shipment->id}")
            ->assertOk()
            ->assertSee('id="shipment-map"', false)
            ->assertSee('Route map');
    }

    public function test_map_data_has_origin_destination_and_only_positions_with_coordinates(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $shipment = $this->shipmentFor($customer, ShipmentStatus::InTransit);

        $this->event($shipment, ['description' => 'Position A', 'occurred_at' => now()->subHours(5), 'latitude' => 1.25, 'longitude' => 103.5]);
        $this->event($shipment, ['description' => 'No coordinates', 'occurred_at' => now()->subHours(4)]);
        $this->event($shipment, ['description' => 'Position B', 'occurred_at' => now()->subHours(3), 'latitude' => 3.0, 'longitude' => 101.4]);

        $this->actingAs($customer)->get("/shipments/{$shipment->id}")
            ->assertOk()
            ->assertViewHas('mapData', function (array $map) use ($shipment) {
                return abs($map['origin']['lat'] - $shipment->origin->latitude) < 1e-9
                    && abs($map['destination']['lng'] - $shipment->destination->longitude) < 1e-9
                    && count($map['points']) === 2
                    && $map['points'][0]['description'] === 'Position A'
                    && $map['points'][1]['description'] === 'Position B'
                    && $map['current']['lat'] === 3.0     // latest event that has coordinates
                    && $map['delivered'] === false;
            });
    }

    public function test_map_data_marks_delivered_shipments(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $shipment = $this->shipmentFor($customer, ShipmentStatus::Delivered);

        $this->actingAs($customer)->get("/shipments/{$shipment->id}")
            ->assertViewHas('mapData', fn (array $map) => $map['delivered'] === true && $map['current'] === null);
    }

    public function test_map_data_is_escaped_in_the_page(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $shipment = $this->shipmentFor($customer, ShipmentStatus::InTransit);

        $this->event($shipment, [
            'description' => '<script>alert(1)</script>',
            'latitude' => 1.0,
            'longitude' => 103.0,
        ]);

        $this->actingAs($customer)->get("/shipments/{$shipment->id}")
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
