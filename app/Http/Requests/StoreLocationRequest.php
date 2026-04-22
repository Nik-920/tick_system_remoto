<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'building' => ['required', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:50'],
            'room_code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('locations', 'room_code')],
            'is_active' => ['nullable', 'boolean'],
            'qr_token' => ['prohibited'],
            'qr_image_url' => ['prohibited'],
            'qr_generation_status' => ['prohibited'],
            'qr_last_error' => ['prohibited'],
            'qr_job_id' => ['prohibited'],
            'qr_generated_at' => ['prohibited'],
        ];
    }
}
