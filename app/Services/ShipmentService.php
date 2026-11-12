<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function __construct(
        private AuditService $audit,
        private EtaService $eta,
    ) {
    }

    /**
     * @param array<string, mixed> $data validated shipment attributes (+ optional "items")
     * @param CarbonInterface|null $at   event time of creation (defaults to now; used by seeders)
     */
    public function create(array $data, User $actor, ?CarbonInterface $at = null): Shipment
    {
        return DB::transaction(function () use ($data, $actor, $at) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $shipment = new Shipment($data);
            $shipment->shipment_number = $this->generateNumber();
            $shipment->status = ShipmentStatus::Created;
            $shipment->estimated_arrival_at = $shipment->expected_arrival_at;
            $shipment->is_delayed = false;
            $shipment->delay_minutes = 0;
            $shipment->created_by = $actor->id;
            $shipment->save();

            foreach ($items as $item) {
                $shipment->items()->create($item);
            }

            $origin = $shipment->origin;

            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'event_type' => ShipmentStatus::Created->value,
                'status' => ShipmentStatus::Created->value,
                'is_exception' => false,
                'location_id' => $origin->id,
                'latitude' => $origin->latitude,
                'longitude' => $origin->longitude,
                'description' => 'Shipment created',
                'occurred_at' => $at ?? now(),
                'created_by' => $actor->id,
            ]);

            $this->audit->log($actor, 'shipment.created', $shipment, null, [
                'shipment_number' => $shipment->shipment_number,
                'customer_id' => $shipment->customer_id,
            ]);

            return $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items']);
        });
    }

    /** Update descriptive fields. Status can NOT be changed here, only through changeStatus(). */
    public function update(Shipment $shipment, array $data, User $actor): Shipment
    {
        unset($data['items']);

        return DB::transaction(function () use ($shipment, $data, $actor) {
            $shipment->fill($data);

            if ($shipment->expected_arrival_at <= $shipment->expected_departure_at) {
                throw ValidationException::withMessages([
                    'expected_arrival_at' => ['The expected arrival must be after the expected departure.'],
                ]);
            }

            $dirty = $shipment->getDirty();
            if ($dirty === []) {
                return $shipment;
            }

            $old = array_intersect_key($shipment->getOriginal(), $dirty);
            $shipment->save();

            $this->audit->log($actor, 'shipment.updated', $shipment, $old, $dirty);

            if (array_key_exists('expected_arrival_at', $dirty)) {
                $this->eta->apply($shipment, null, $actor);
            }

            return $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items']);
        });
    }

    /**
     * The ONLY place where a shipment status changes. Validates against the
     * transition map in ShipmentStatus; terminal states cannot be left.
     */
    public function changeStatus(
        Shipment $shipment,
        ShipmentStatus $to,
        User $actor,
        ?CarbonInterface $at = null
    ): Shipment {
        $from = $shipment->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidStatusTransitionException(
                $from->value,
                $to->value,
                'status',
                array_map(fn (ShipmentStatus $s) => $s->value, $from->allowedTransitions())
            );
        }

        $shipment->status = $to;

        if ($to === ShipmentStatus::Delivered) {
            $shipment->delivered_at = $at ?? now();
        }

        $shipment->save();

        $this->audit->log(
            $actor,
            'shipment.status_changed',
            $shipment,
            ['status' => $from->value],
            ['status' => $to->value]
        );

        // Delivery fixes the actual arrival time, so re-evaluate delay against it.
        if ($to === ShipmentStatus::Delivered || $to === ShipmentStatus::Cancelled) {
            $this->eta->apply($shipment, null, $actor);
        }

        return $shipment;
    }

    private function generateNumber(): string
    {
        do {
            $number = 'CT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Shipment::where('shipment_number', $number)->exists());

        return $number;
    }
}