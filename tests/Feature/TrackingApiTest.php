<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFixtures;
use Tests\TestCase;

class TrackingApiTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_valid_lifecycle_reaches_delivered(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer));
        $this->actingAsRole(UserRole::Operator);

        foreach (['BOOKED' => 5, 'PICKED_UP' => 4, 'IN_TRANSIT' => 3, 'DELIVERED' => 2] as $status => $hoursAgo) {
            $this->postJson("/api/shipments/{$shipment->id}/tracking", [
                'status' => $status,
                'occurred_at' => now()->subHours($hoursAgo)->toIso8601String(),
            ])->assertCreated()->assertJsonPath('data.status', $status);
        }

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Delivered, $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertDatabaseCount('tracking_events', 4);
    }

    public function test_invalid_status_transition_is_rejected_and_nothing_is_stored(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer));
        $this->actingAsRole(UserRole::Operator);

        $this->postJson("/api/shipments/{$shipment->id}/tracking", [
            'status' => 'DELIVERED',
            'occurred_at' => now()->subHour()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->assertSame(ShipmentStatus::Created, $shipment->fresh()->status);
        $this->assertDatabaseCount('tracking_events', 0);
    }

    public function test_terminal_shipment_cannot_go_back_or_receive_events(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer), ShipmentStatus::Delivered);
        $this->actingAsRole(UserRole::Operator);

        $this->postJson("/api/shipments/{$shipment->id}/tracking", [
            'status' => 'CREATED',
            'occurred_at' => now()->subHour()->toIso8601String(),
        ])->assertUnprocessable();

        $this->assertSame(ShipmentStatus::Delivered, $shipment->fresh()->status);
    }

    public function test_event_time_must_not_be_earlier_than_the_latest_event(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer), ShipmentStatus::InTransit);
        $this->actingAsRole(UserRole::Operator);

        $this->postJson("/api/shipments/{$shipment->id}/tracking", [
            'event_type' => 'UPDATE', 'occurred_at' => now()->subHour()->toIso8601String(),
        ])->assertCreated();

        $this->postJson("/api/shipments/{$shipment->id}/tracking", [
            'event_type' => 'UPDATE', 'occurred_at' => now()->subHours(2)->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['occurred_at']);
    }

    public function test_event_time_cannot_be_in_the_future(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer), ShipmentStatus::InTransit);
        $this->actingAsRole(UserRole::Operator);

        $this->postJson("/api/shipments/{$shipment->id}/tracking", [
            'event_type' => 'UPDATE', 'occurred_at' => now()->addDay()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['occurred_at']);
    }

    public function test_new_events_append_and_never_overwrite_history(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer), ShipmentStatus::InTransit);
        $this->actingAsRole(UserRole::Operator);

        $first = $this->postJson("/api/shipments/{$shipment->id}/tracking", [
            'event_type' => 'UPDATE', 'description' => 'first', 'occurred_at' => now()->subHours(3)->toIso8601String(),
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/shipments/{$shipment->id}/tracking", [
            'event_type' => 'UPDATE', 'description' => 'second', 'occurred_at' => now()->subHours(2)->toIso8601String(),
        ])->assertCreated();

        $this->assertDatabaseCount('tracking_events', 2);
        $this->assertDatabaseHas('tracking_events', ['id' => $first, 'description' => 'first']);
    }

    public function test_timeline_is_chronological_and_reports_current_location(): void
    {
        $shipment = $this->shipmentFor($this->userWithRole(UserRole::Customer), ShipmentStatus::InTransit);
        $this->actingAsRole(UserRole::Operator);

        $post = fn (array $d) => $this->postJson("/api/shipments/{$shipment->id}/tracking", $d + ['event_type' => 'UPDATE'])->assertCreated();

        $post(['description' => 'A', 'occurred_at' => now()->subHours(5)->toIso8601String(), 'latitude' => 1.25, 'longitude' => 103.5]);
        $post(['description' => 'B (no coordinates)', 'occurred_at' => now()->subHours(3)->toIso8601String()]);

        $response = $this->getJson("/api/shipments/{$shipment->id}/tracking")->assertOk();

        $this->assertSame(['A', 'B (no coordinates)'], array_column($response->json('data'), 'description'));
        // latest event WITH coordinates, not simply the latest event
        $response->assertJsonPath('meta.current_location.latitude', 1.25)
            ->assertJsonPath('meta.current_location.longitude', 103.5);
    }

    public function test_customer_cannot_add_events_but_can_read_their_timeline(): void
    {
        $customer = $this->actingAsRole(UserRole::Customer);
        $shipment = $this->shipmentFor($customer, ShipmentStatus::InTransit);

        $this->postJson("/api/shipments/{$shipment->id}/tracking", [
            'event_type' => 'UPDATE', 'occurred_at' => now()->subHour()->toIso8601String(),
        ])->assertForbidden();

        $this->getJson("/api/shipments/{$shipment->id}/tracking")->assertOk();
    }
}
