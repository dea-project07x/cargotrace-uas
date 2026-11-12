<?php

namespace App\Http\Requests;

use App\Enums\ExceptionType;
use App\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageExceptions', $this->route('shipment'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ExceptionType::class)],
            'severity' => ['required', Rule::enum(Severity::class)],
            'description' => ['required', 'string', 'max:2000'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ];
    }
}
