<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_number' => $this->shipment_number,
            'status' => $this->status->value,
            'cargo_type' => $this->cargo_type,
            'weight_kg' => (float) $this->weight_kg,
            'volume_cbm' => $this->volume_cbm !== null ? (float) $this->volume_cbm : null,
            'expected_departure_at' => $this->expected_departure_at?->toIso8601String(),
            'expected_arrival_at' => $this->expected_arrival_at?->toIso8601String(),
            'estimated_arrival_at' => $this->estimated_arrival_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'delay' => [
                'is_delayed' => $this->is_delayed,
                'minutes' => $this->delay_minutes,
                'severity' => $this->delay_severity?->value,
                'reason' => $this->delay_reason,
            ],
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'carrier' => CarrierResource::make($this->whenLoaded('carrier')),
            'origin' => LocationResource::make($this->whenLoaded('origin')),
            'destination' => LocationResource::make($this->whenLoaded('destination')),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_weight_kg' => $i->unit_weight_kg !== null ? (float) $i->unit_weight_kg : null,
            ])),
            'current_location' => $this->when(
                $this->relationLoaded('trackingEvents'),
                fn () => ($e = $this->currentLocationEvent()) ? [
                    'latitude' => $e->latitude,
                    'longitude' => $e->longitude,
                    'occurred_at' => $e->occurred_at->toIso8601String(),
                ] : null
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
