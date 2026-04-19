<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in($this->allowedRoles())],
            'avatar_file' => ['sometimes', 'nullable', 'file', 'image', 'max:2048'],
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedRoles(): array
    {
        return ['reporter', 'maintenance', 'admin', 'super_admin'];
    }
}
