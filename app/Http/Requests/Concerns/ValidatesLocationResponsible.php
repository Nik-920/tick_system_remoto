<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas (Store/Update de ubicaciones) para el campo opcional
 * responsible_user_id: debe existir y tener rol maintenance. Se mantiene en un
 * trait para que ambos requests validen exactamente igual.
 */
trait ValidatesLocationResponsible
{
    /**
     * @return array<int, mixed>
     */
    protected function responsibleUserRules(): array
    {
        return [
            'nullable',
            'uuid',
            Rule::exists('users', 'id'),
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                $user = User::find($value);

                if ($user === null || ! method_exists($user, 'hasRole') || ! $user->hasRole('maintenance')) {
                    $fail('El responsable de la ubicación debe ser un usuario con rol maintenance.');
                }
            },
        ];
    }
}
