<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El assignee debe tener rol maintenance. El service también lo valida
 * (defensa en profundidad); aquí se asegura la paridad de validación
 * Web/API y un 422 consistente. El mensaje debe coincidir con el del
 * service para no divergir.
 */
class AssigneeHasMaintenanceRole implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $target = User::query()->find($value);

        if ($target === null) {
            // La regla 'exists' ya reporta el usuario inexistente.
            return;
        }

        if (! $target->hasRole('maintenance')) {
            $fail('El usuario asignado debe tener rol maintenance.');
        }
    }
}
