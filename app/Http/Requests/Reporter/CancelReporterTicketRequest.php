<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporter;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a reporter cancelling their OWN still-open request.
 *
 * Only an optional free-text reason is accepted. Ownership + the cancellation
 * window (own + open + unassigned + unlocked) are enforced by the controller via
 * TicketPolicy@cancelAsReporter; the state change itself is performed by
 * TicketCancellationService.
 */
class CancelReporterTicketRequest extends FormRequest
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
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'comment' => 'comentario',
        ];
    }
}
