<?php

namespace App\Http\Requests;

use App\Enums\ShipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addTracking', $this->route('shipment'));
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(ShipmentStatus::class)],
            'event_type' => ['required_without:status', 'nullable', 'string', 'max:50'],
            'occurred_at' => ['required', 'date'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'description' => ['nullable', 'string', 'max:1000'],
            'estimated_arrival_at' => ['nullable', 'date'],
        ];
    }
}
