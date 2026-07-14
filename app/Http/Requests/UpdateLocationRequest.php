<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesLocationResponsible;
use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
{
    use ValidatesLocationResponsible;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'building' => ['sometimes', 'required', 'string', 'max:255'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'room_code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('locations', 'room_code')->ignore($this->locationId()),
            ],
            'responsible_user_id' => array_merge(['sometimes'], $this->responsibleUserRules()),
            'is_active' => ['sometimes', 'boolean'],
            'confirm_similar_location' => ['nullable', 'boolean'],
            'qr_token' => ['prohibited'],
            'qr_image_url' => ['prohibited'],
            'qr_generation_status' => ['prohibited'],
            'qr_last_error' => ['prohibited'],
            'qr_job_id' => ['prohibited'],
            'qr_generated_at' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('is_active')) {
            $this->merge([
                'is_active' => $this->boolean('is_active'),
            ]);
        }
    }

    private function locationId(): ?string
    {
        $location = $this->route('location');

        if ($location instanceof Location) {
            return $location->id;
        }

        return is_string($location) ? $location : null;
    }
}
