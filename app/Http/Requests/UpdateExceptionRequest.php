<?php

namespace App\Http\Requests;

use App\Enums\ExceptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('exception'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ExceptionStatus::class)],
            'resolution_notes' => ['required_if:status,RESOLVED', 'nullable', 'string', 'max:2000'],
        ];
    }
}
