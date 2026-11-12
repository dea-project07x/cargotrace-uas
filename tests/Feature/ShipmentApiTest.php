<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFixtures;
use Tests\TestCase;

class ShipmentApiTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_operator_can_create_a_shipment(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $this->actingAsRole(UserRole::Operator);

        $response = $this->postJson('/api/shipments', $this->shipmentPayload($customer))
            ->assertCreated()
            ->assertJsonPath('data.status', 'CREATED')
            ->assertJsonPath('data.delay.is_delayed', false);

        $this->assertStringStartsWith('CT-', $response->json('data.shipment_number'));
        $this->assertDatabaseCount('shipment_items', 1);
        $this->assertDatabaseHas('tracking_events', ['event_type' => 'CREATED']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.created']);
    }

    public function test_customer_cannot_create_a_shipment(): void
    {
        $customer = $this->actingAsRole(UserRole::Customer);

        $this->postJson('/api/shipments', $this->shipmentPayload($customer))->assertForbidden();
    }

    public function test_creating_a_shipment_validates_input(): void
    {
        $this->actingAsRole(UserRole::Operator);

        $this->postJson('/api/shipments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'carrier_id', 'origin_id', 'destination_id', 'cargo_type', 'weight_kg']);
    }

    public function test_arrival_must_be_after_departure(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $this->actingAsRole(UserRole::Operator);

        $payload = $this->shipmentPayload($customer);
        $payload['expected_arrival_at'] = now()->subDay()->toIso8601String();

        $this->postJson('/api/shipments', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expected_arrival_at']);
    }

    public function test_status_cannot_be_set_through_create_or_update(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $this->actingAsRole(UserRole::Operator);

        $payload = $this->shipmentPayload($customer) + ['status' => 'DELIVERED'];
        $id = $this->postJson('/api/shipments', $payload)->assertCreated()->json('data.id');

        $this->patchJson("/api/shipments/{$id}", ['status' => 'DELIVERED', 'cargo_type' => 'Books'])->assertOk();

        $this->assertDatabaseHas('shipments', ['id' => $id, 'status' => 'CREATED', 'cargo_type' => 'Books']);
    }

    public function test_customer_only_sees_their_own_shipments(): void
    {
        $alice = $this->actingAsRole(UserRole::Customer);
        $bob = $this->userWithRole(UserRole::Customer);

        $own = $this->shipmentFor($alice);
        $this->shipmentFor($bob);

        $this->getJson('/api/shipments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_customer_cannot_view_someone_elses_shipment(): void
    {
        $this->actingAsRole(UserRole::Customer);
        $other = $this->shipmentFor($this->userWithRole(UserRole::Customer));

        $this->getJson("/api/shipments/{$other->id}")->assertForbidden();
        $this->getJson("/api/shipments/{$other->id}/tracking")->assertForbidden();
        $this->getJson("/api/shipments/{$other->id}/exceptions")->assertForbidden();
    }

    public function test_customer_can_view_their_own_shipment(): void
    {
        $alice = $this->actingAsRole(UserRole::Customer);
        $shipment = $this->shipmentFor($alice);

        $this->getJson("/api/shipments/{$shipment->id}")
            ->assertOk()
            ->assertJsonPath('data.shipment_number', $shipment->shipment_number);
    }

    public function test_only_admin_can_delete_and_only_early_stage_shipments(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $created = $this->shipmentFor($customer);
        $inTransit = $this->shipmentFor($customer, ShipmentStatus::InTransit);

        $this->actingAsRole(UserRole::Operator);
        $this->deleteJson("/api/shipments/{$created->id}")->assertForbidden();

        $this->actingAsRole(UserRole::Admin);
        $this->deleteJson("/api/shipments/{$inTransit->id}")->assertForbidden();
        $this->deleteJson("/api/shipments/{$created->id}")->assertNoContent();

        $this->assertDatabaseMissing('shipments', ['id' => $created->id]);
        $this->assertDatabaseHas('shipments', ['id' => $inTransit->id]);
    }

    public function test_operator_cannot_update_a_terminal_shipment(): void
    {
        $delivered = $this->shipmentFor($this->userWithRole(UserRole::Customer), ShipmentStatus::Delivered);
        $this->actingAsRole(UserRole::Operator);

        $this->patchJson("/api/shipments/{$delivered->id}", ['cargo_type' => 'x'])->assertForbidden();
    }

    public function test_search_filter_sort_and_pagination(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $a = $this->shipmentFor($customer, ShipmentStatus::InTransit);
        $this->shipmentFor($customer, ShipmentStatus::Created);
        $this->shipmentFor($customer, ShipmentStatus::Created);

        $this->actingAsRole(UserRole::Operator);

        $this->getJson('/api/shipments?status=IN_TRANSIT')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/shipments?search='.$a->shipment_number)->assertOk()->assertJsonPath('data.0.id', $a->id);
        $this->getJson('/api/shipments?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
        $this->getJson('/api/shipments?sort=shipment_number&direction=asc')->assertOk();
        $this->getJson('/api/shipments?sort=password')->assertUnprocessable(); // sort whitelist
        $this->getJson('/api/shipments?status=NOPE')->assertUnprocessable();
    }
}
