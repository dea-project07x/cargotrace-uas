<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrackingService
{
    public function __construct(
        private ShipmentService $shipments,
        private EtaService $eta,
        private AuditService $audit,
    ) {
    }

    /**
     * Append a tracking event. History is never overwritten.
     *
     * Rules: shipment must not be terminal; occurred_at may not be in the
     * future nor earlier than the latest event; a status that differs from the
     * current one must be a valid transition (else InvalidStatusTransitionException).
     *
     * @param array<string, mixed> $data
     */
    public function addEvent(Shipment $shipment, array $data, User $actor): TrackingEvent
    {
        return DB::transaction(function () use ($shipment, $data, $actor) {
            // Lock the row so two concurrent events cannot both pass the checks.
            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ($shipment->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'shipment' => ["Cannot add tracking events to a {$shipment->status->value} shipment."],
                ]);
            }

            $occurredAt = Carbon::parse($data['occurred_at']);

            if ($occurredAt->gt(now()->addMinutes(5))) {
                throw ValidationException::withMessages([
                    'occurred_at' => ['The event time cannot be in the future.'],
                ]);
            }

            $last = $shipment->trackingEvents()->reorder()
                ->orderByDesc('occurred_at')->orderByDesc('id')->first();

            if ($last && $occurredAt->lt($last->occurred_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => [
                        'The event time cannot be earlier than the latest event ('
                        .$last->occurred_at->toIso8601String().').',
                    ],
                ]);
            }

            $status = ! empty($data['status']) ? ShipmentStatus::from($data['status']) : null;

            if ($status !== null && $status !== $shipment->status) {
                $this->shipments->changeStatus($shipment, $status, $actor, $occurredAt);
            }

            $latitude = $data['latitude'] ?? null;
            $longitude = $data['longitude'] ?? null;
            $locationId = $data['location_id'] ?? null;

            if ($locationId && ($latitude === null || $longitude === null)) {
                $location = Location::find($locationId);
                $latitude = $location?->latitude;
                $longitude = $location?->longitude;
            }

            $event = TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'event_type' => $status?->value ?? ($data['event_type'] ?? 'UPDATE'),
                'status' => $status?->value,
                'is_exception' => false,
                'location_id' => $locationId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'description' => $data['description'] ?? null,
                'occurred_at' => $occurredAt,
                'created_by' => $actor->id,
            ]);

            if (! empty($data['estimated_arrival_at'])) {
                $this->eta->apply($shipment, Carbon::parse($data['estimated_arrival_at']), $actor);
            }

            $this->audit->log($actor, 'tracking.added', $shipment, null, [
                'event_id' => $event->id,
                'event_type' => $event->event_type,
            ]);

            return $event;
        });
    }
}
