<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
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
            'role' => ['required', 'string', Rule::in($this->allowedRoles())],
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
