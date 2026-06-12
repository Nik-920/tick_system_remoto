<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
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
                // El assignee debe tener rol maintenance. El service también lo valida
                // (defensa en profundidad); aquí se asegura la paridad de validación
                // Web/API y un 422 consistente. El mensaje debe coincidir con el del
                // service para no divergir.
                function (string $_attribute, mixed $value, Closure $fail): void {
                    $target = User::query()->find($value);

                    if ($target === null) {
                        // La regla 'exists' ya reporta el usuario inexistente.
                        return;
                    }

                    if (! $target->hasRole('maintenance')) {
                        $fail('El usuario asignado debe tener rol maintenance.');
                    }
                },
            ],
        ];
    }
}
