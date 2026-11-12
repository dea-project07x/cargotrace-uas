<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('shipment'));
    }

    /** Note: "status" is deliberately absent; status only changes via tracking events. */
    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'integer', Rule::exists('users', 'id')->where('role', 'customer')],
            'carrier_id' => ['sometimes', 'integer', Rule::exists('carriers', 'id')->where('is_active', true)],
            'origin_id' => ['sometimes', 'integer', 'exists:locations,id'],
            'destination_id' => ['sometimes', 'integer', 'exists:locations,id'],
            'cargo_type' => ['sometimes', 'string', 'max:100'],
            'weight_kg' => ['sometimes', 'numeric', 'min:0.01', 'max:10000000'],
            'volume_cbm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10000000'],
            'expected_departure_at' => ['sometimes', 'date'],
            'expected_arrival_at' => ['sometimes', 'date'],
        ];
    }
}
