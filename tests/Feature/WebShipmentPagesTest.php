<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFixtures;
use Tests\TestCase;

class WebShipmentPagesTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_customer_list_only_contains_their_own_shipments(): void
    {
        $alice = $this->userWithRole(UserRole::Customer);
        $bob = $this->userWithRole(UserRole::Customer);
        $own = $this->shipmentFor($alice);
        $other = $this->shipmentFor($bob);

        $this->actingAs($alice)->get('/shipments')
            ->assertOk()
            ->assertSee($own->shipment_number)
            ->assertDontSee($other->shipment_number);
    }

    public function test_staff_see_all_shipments_and_can_filter_by_status(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $booked = $this->shipmentFor($customer, ShipmentStatus::Booked);
        $created = $this->shipmentFor($customer, ShipmentStatus::Created);

        $operator = $this->userWithRole(UserRole::Operator);

        $this->actingAs($operator)->get('/shipments')
            ->assertOk()->assertSee($booked->shipment_number)->assertSee($created->shipment_number);

        $this->actingAs($operator)->get('/shipments?status=BOOKED')
            ->assertOk()->assertSee($booked->shipment_number)->assertDontSee($created->shipment_number);
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $this->actingAs($this->userWithRole(UserRole::Operator))
            ->get('/shipments?sort=password')
            ->assertSessionHasErrors('sort');
    }

    public function test_detail_page_shows_timeline_and_exceptions(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $shipment = $this->shipmentFor($customer, ShipmentStatus::InTransit);

        TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'event_type' => 'UPDATE',
            'is_exception' => false,
            'description' => 'Vessel departed Singapore',
            'occurred_at' => now()->subHours(3),
            'created_by' => $shipment->created_by,
        ]);

        ShipmentException::forceCreate([
            'shipment_id' => $shipment->id,
            'type' => 'CUSTOMS_HOLD',
            'severity' => 'HIGH',
            'status' => 'OPEN',
            'description' => 'Documents under inspection.',
            'detected_at' => now()->subHour(),
            'reported_by' => $shipment->created_by,
        ]);

        $this->actingAs($customer)->get("/shipments/{$shipment->id}")
            ->assertOk()
            ->assertSee($shipment->shipment_number)
            ->assertSee('Vessel departed Singapore')
            ->assertSee('Customs Hold')
            ->assertSee('Documents under inspection.');
    }

    public function test_customer_cannot_open_someone_elses_shipment(): void
    {
        $alice = $this->userWithRole(UserRole::Customer);
        $other = $this->shipmentFor($this->userWithRole(UserRole::Customer));

        $this->actingAs($alice)->get("/shipments/{$other->id}")->assertForbidden();
    }

    public function test_user_supplied_text_is_escaped(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $shipment = $this->shipmentFor($customer, ShipmentStatus::InTransit);

        TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'event_type' => 'UPDATE',
            'is_exception' => false,
            'description' => '<script>alert(1)</script>',
            'occurred_at' => now()->subHour(),
            'created_by' => $shipment->created_by,
        ]);

        $this->actingAs($customer)->get("/shipments/{$shipment->id}")
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
