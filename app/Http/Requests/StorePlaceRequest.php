<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'block_id' => ['required', 'exists:blocks,id'],
            'code' => ['required', 'string', 'max:50', 'unique:places,code'],
            'name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'surface' => ['nullable', 'numeric', 'min:0'],
            'type' => ['nullable', Rule::in(['STANDARD', 'KIOSK', 'BOUTIQUE', 'STALL', 'WAREHOUSE', 'OTHER'])],
            'status' => ['nullable', Rule::in(['AVAILABLE', 'OCCUPIED', 'MAINTENANCE', 'INACTIVE'])],
        ];
    }
}
