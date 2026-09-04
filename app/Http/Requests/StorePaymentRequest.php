<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_id' => ['required', 'exists:merchants,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank_id' => ['required', 'exists:banks,id'],
            'reference_number' => ['required', 'string', 'max:100', 'unique:payments,reference_number'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'received_by' => ['nullable', 'exists:users,id'],
            'auto_allocate' => ['nullable', 'boolean'],
            'as_of_date' => ['nullable', 'date'],
            'period_year' => ['nullable', 'integer', 'min:2000'],
            'period_month' => ['nullable', 'integer', 'between:1,12'],
            'allocations' => ['nullable', 'array', 'min:1'],
            'allocations.*.rent_obligation_id' => ['required', 'exists:rent_obligations,id'],
            'allocations.*.amount_allocated' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
