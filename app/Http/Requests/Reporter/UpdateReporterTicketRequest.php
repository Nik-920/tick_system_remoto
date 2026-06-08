<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporter;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for a reporter editing their OWN still-open request.
 *
 * Only the safe, reporter-facing fields are accepted. Operational fields
 * (state, assigned_to, assignment_locked, assignment_source, reporter_id,
 * resolved_at, state_history) are NOT part of the rules, so they can never
 * reach the model via validated() — even if a crafted payload includes them.
 *
 * Ownership + editability (own + open + unassigned + unlocked) are enforced by
 * the controller against the resolved ticket through TicketPolicy@update; this
 * request only shapes/sanitizes the input.
 */
class UpdateReporterTicketRequest extends FormRequest
{
    /** @var list<string> */
    private const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public function authorize(): bool
    {
        // The route is gated by auth + role:reporter and the controller performs
        // the per-ticket policy check. Here we only require an authenticated user.
        return $this->user() !== null;
    }

    /**
     * Normalize text inputs to plain text before validation (mirror StoreTicketRequest).
     */
    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $description = $this->input('description');

        $this->merge([
            'title' => $this->sanitizePlainText(is_string($title) ? $title : null),
            'description' => $this->sanitizePlainText(is_string($description) ? $description : null),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'titulo',
            'description' => 'descripcion',
            'location_id' => 'ubicacion',
            'category_id' => 'categoria',
            'priority' => 'prioridad',
        ];
    }

    private function sanitizePlainText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim(strip_tags($value));

        return $clean === '' ? null : $clean;
    }
}
