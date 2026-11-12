<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'status' => $this->status,
            'is_exception' => $this->is_exception,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location' => LocationResource::make($this->whenLoaded('location')),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator->name),
        ];
    }
}
