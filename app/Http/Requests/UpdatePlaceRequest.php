<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'block_id' => ['sometimes', 'exists:blocks,id'],
            'code' => ['sometimes', 'string', 'max:50', 'unique:places,code,' . $this->route('place')->id],
            'name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'surface' => ['nullable', 'numeric', 'min:0'],
            'type' => ['sometimes', Rule::in(['STANDARD', 'KIOSK', 'BOUTIQUE', 'STALL', 'WAREHOUSE', 'OTHER'])],
            'status' => ['sometimes', Rule::in(['AVAILABLE', 'OCCUPIED', 'MAINTENANCE', 'INACTIVE'])],
        ];
    }
}
