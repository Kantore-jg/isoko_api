<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'place_id' => ['required', 'exists:places,id'],
            'merchant_id' => ['required', 'exists:merchants,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'rent_rate_id' => ['nullable', 'exists:rent_rates,id'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'ENDED', 'CANCELLED'])],
            'assignment_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
