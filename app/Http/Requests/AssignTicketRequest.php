<?php

namespace App\Http\Requests;

use App\Rules\AssigneeHasMaintenanceRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => [
                'required',
                'uuid',
                'exists:users,id',
                new AssigneeHasMaintenanceRole,
            ],
        ];
    }
}
