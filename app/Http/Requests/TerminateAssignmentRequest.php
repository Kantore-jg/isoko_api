<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TerminateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'end_date' => ['required', 'date', 'after_or_equal:' . $this->route('assignment')->start_date->format('Y-m-d')],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
