<?php

namespace App\Http\Requests;

use App\Models\TicketEmbedding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewDuplicateRequest extends FormRequest
{
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
            'review_status' => ['required', Rule::in(TicketEmbedding::REVIEW_STATUSES)],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'review_status.required' => 'El estado de revisión es obligatorio.',
            'review_status.in' => 'El estado de revisión debe ser "confirmed" o "dismissed".',
            'review_note.max' => 'La nota no puede superar los 1000 caracteres.',
        ];
    }
}
