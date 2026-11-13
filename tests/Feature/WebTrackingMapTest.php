<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\TrackingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFixtures;
use Tests\TestCase;

class WebTrackingMapTest extends TestCase
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

    public function test_guests_cannot_open_the_map_or_its_data(): void
    {
        $this->get('/tracking')->assertRedirect('/login');
        $this->getJson('/tracking/data')->assertUnauthorized();
    }

    public function test_the_map_page_renders_for_logged_in_users(): void
    {
        $this->actingAs($this->userWithRole(UserRole::Operator))
            ->get('/tracking')
            ->assertOk()
            ->assertSee('Live tracking')
            ->assertSee('id="tracking-map"', false);
    }

    public function test_customers_only_receive_their_own_shipments_without_customer_names(): void
    {
        $alice = $this->userWithRole(UserRole::Customer);
        $bob = $this->userWithRole(UserRole::Customer);
        $own = $this->shipmentFor($alice, ShipmentStatus::InTransit);
        $this->shipmentFor($bob, ShipmentStatus::InTransit);

        $this->actingAs($alice)->getJson('/tracking/data')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.customer', null);
    }

    public function test_staff_receive_positions_path_and_customer_name(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $shipment = $this->shipmentFor($customer, ShipmentStatus::InTransit);

        $this->event($shipment, ['description' => 'Position A', 'occurred_at' => now()->subHours(5), 'latitude' => 1.25, 'longitude' => 103.5]);
        $this->event($shipment, ['description' => 'No coordinates', 'occurred_at' => now()->subHours(4)]);
        $this->event($shipment, ['description' => 'Position B', 'occurred_at' => now()->subHours(3), 'latitude' => 3.0, 'longitude' => 101.4]);

        $this->actingAs($this->userWithRole(UserRole::Operator))->getJson('/tracking/data')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer', $customer->name)
            ->assertJsonPath('data.0.status', 'IN_TRANSIT')
            ->assertJsonCount(2, 'data.0.points')
            ->assertJsonPath('data.0.points.0.description', 'Position A')
            ->assertJsonPath('data.0.points.1.description', 'Position B')
            ->assertJsonPath('data.0.current.lat', fn ($lat) => abs($lat - 3.0) < 1e-9)
            ->assertJsonPath('data.0.url', route('shipments.show', $shipment));
    }

    public function test_shipments_without_any_position_have_no_current_location(): void
    {
        $this->shipmentFor($this->userWithRole(UserRole::Customer));

        $this->actingAs($this->userWithRole(UserRole::Admin))->getJson('/tracking/data')
            ->assertOk()
            ->assertJsonPath('data.0.current', null)
            ->assertJsonPath('data.0.points', []);
    }

    public function test_delay_information_is_part_of_the_feed(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer), ShipmentStatus::InTransit);
        $shipment->forceFill([
            'is_delayed' => true,
            'delay_minutes' => 420,
            'delay_severity' => 'MEDIUM',
            'delay_reason' => 'Delayed (MEDIUM): test reason',
        ])->save();

        $this->actingAs($this->userWithRole(UserRole::Operator))->getJson('/tracking/data')
            ->assertOk()
            ->assertJsonPath('data.0.is_delayed', true)
            ->assertJsonPath('data.0.delay_severity', 'MEDIUM')
            ->assertJsonPath('data.0.delay_reason', 'Delayed (MEDIUM): test reason');
    }
}
