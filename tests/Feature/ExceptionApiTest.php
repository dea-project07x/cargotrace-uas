<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFixtures;
use Tests\TestCase;

class ExceptionApiTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    private function payload(): array
    {
        return ['type' => 'CUSTOMS_HOLD', 'severity' => 'HIGH', 'description' => 'Documents under inspection.'];
    }

    public function test_operator_can_open_an_exception_and_timeline_marks_it(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $shipment = $this->shipmentFor($customer, ShipmentStatus::InTransit);
        $this->actingAsRole(UserRole::Operator);

        $this->postJson("/api/shipments/{$shipment->id}/exceptions", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'OPEN')
            ->assertJsonPath('data.type', 'CUSTOMS_HOLD');

        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $shipment->id, 'is_exception' => true]);
        $this->assertSame(1, $customer->notifications()->count());
    }

    public function test_exception_input_is_validated(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer), ShipmentStatus::InTransit);
        $this->actingAsRole(UserRole::Operator);

        $this->postJson("/api/shipments/{$shipment->id}/exceptions", ['type' => 'ALIENS', 'severity' => 'HIGH'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'description']);
    }

    public function test_customer_cannot_open_or_update_exceptions_but_can_list_their_own(): void
    {
        $customer = $this->actingAsRole(UserRole::Customer);
        $shipment = $this->shipmentFor($customer, ShipmentStatus::InTransit);

        $this->postJson("/api/shipments/{$shipment->id}/exceptions", $this->payload())->assertForbidden();
        $this->getJson("/api/shipments/{$shipment->id}/exceptions")->assertOk();

        $this->actingAsRole(UserRole::Operator);
        $id = $this->postJson("/api/shipments/{$shipment->id}/exceptions", $this->payload())->json('data.id');

        $this->actingAsRole(UserRole::Customer);
        $this->patchJson("/api/exceptions/{$id}", ['status' => 'INVESTIGATING'])->assertForbidden();
    }

    public function test_exception_goes_open_investigating_resolved_with_timestamps(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer), ShipmentStatus::InTransit);
        $this->actingAsRole(UserRole::Operator);

        $id = $this->postJson("/api/shipments/{$shipment->id}/exceptions", $this->payload())->json('data.id');

        $this->patchJson("/api/exceptions/{$id}", ['status' => 'INVESTIGATING'])
            ->assertOk()->assertJsonPath('data.status', 'INVESTIGATING')->assertJsonPath('data.resolved_at', null);

        $this->patchJson("/api/exceptions/{$id}", ['status' => 'RESOLVED', 'resolution_notes' => 'Cleared by customs.'])
            ->assertOk()->assertJsonPath('data.status', 'RESOLVED');

        $this->assertNotNull(\App\Models\ShipmentException::find($id)->resolved_at);
    }

    public function test_resolving_requires_notes_and_resolved_is_terminal(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer), ShipmentStatus::InTransit);
        $this->actingAsRole(UserRole::Operator);
        $id = $this->postJson("/api/shipments/{$shipment->id}/exceptions", $this->payload())->json('data.id');

        $this->patchJson("/api/exceptions/{$id}", ['status' => 'RESOLVED'])
            ->assertUnprocessable()->assertJsonValidationErrors(['resolution_notes']);

        $this->patchJson("/api/exceptions/{$id}", ['status' => 'RESOLVED', 'resolution_notes' => 'ok'])->assertOk();

        $this->patchJson("/api/exceptions/{$id}", ['status' => 'OPEN'])->assertUnprocessable();
    }
}
