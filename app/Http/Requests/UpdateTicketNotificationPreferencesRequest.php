<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['nullable', 'array'],
            'preferences.*.*' => ['nullable'],
        ];
    }
}
