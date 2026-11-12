<?php

namespace Tests\Feature;

use App\Enums\Severity;
use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFixtures;
use Tests\TestCase;

class DelayFlowTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_revised_eta_flags_the_shipment_as_delayed_and_notifies(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $shipment = $this->shipmentFor($customer, ShipmentStatus::InTransit);
        $this->actingAsRole(UserRole::Operator);

        $newEta = $shipment->expected_arrival_at->copy()->addHours(7);

        $this->postJson("/api/shipments/{$shipment->id}/tracking", [
            'event_type' => 'UPDATE',
            'occurred_at' => now()->subHour()->toIso8601String(),
            'estimated_arrival_at' => $newEta->toIso8601String(),
        ])->assertCreated();

        $shipment->refresh();
        $this->assertTrue($shipment->is_delayed);
        $this->assertSame(420, $shipment->delay_minutes);
        $this->assertSame(Severity::Medium, $shipment->delay_severity);
        $this->assertStringContainsString('MEDIUM', $shipment->delay_reason);

        $this->assertSame(1, $customer->notifications()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.delay_evaluated']);
    }

    public function test_recovering_the_eta_clears_the_delay(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer), ShipmentStatus::InTransit);
        $this->actingAsRole(UserRole::Operator);

        $this->postJson("/api/shipments/{$shipment->id}/tracking", [
            'event_type' => 'UPDATE',
            'occurred_at' => now()->subHours(2)->toIso8601String(),
            'estimated_arrival_at' => $shipment->expected_arrival_at->copy()->addHours(30)->toIso8601String(),
        ])->assertCreated();
        $this->assertSame(Severity::High, $shipment->fresh()->delay_severity);

        $this->postJson("/api/shipments/{$shipment->id}/tracking", [
            'event_type' => 'UPDATE',
            'occurred_at' => now()->subHour()->toIso8601String(),
            'estimated_arrival_at' => $shipment->expected_arrival_at->toIso8601String(),
        ])->assertCreated();

        $fresh = $shipment->fresh();
        $this->assertFalse($fresh->is_delayed);
        $this->assertNull($fresh->delay_severity);
    }

    public function test_late_delivery_is_judged_on_actual_delivery_time(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer), ShipmentStatus::InTransit);
        $shipment->update(['expected_arrival_at' => now()->subHours(10)]);
        $this->actingAsRole(UserRole::Operator);

        $this->postJson("/api/shipments/{$shipment->id}/tracking", [
            'status' => 'DELIVERED',
            'occurred_at' => now()->subHours(2)->toIso8601String(),
        ])->assertCreated();

        $fresh = $shipment->fresh();
        $this->assertTrue($fresh->is_delayed);          // delivered 8h after expected
        $this->assertSame(Severity::Medium, $fresh->delay_severity);
    }
}
