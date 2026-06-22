<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporter;

use App\Support\Functional\UploadRules;
use App\Support\Functional\UploadValidationPipeline;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
 *
 * Image uploads (new_images[]) are validated here but stored by the controller.
 * The reporter cannot delete existing evidence from this endpoint; that would
 * require a dedicated destroy route per media item.
 */
class UpdateReporterTicketRequest extends FormRequest
{
    /** @var list<string> */
    private const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const MAX_EVIDENCE_FILES = 5;

    public const MAX_EVIDENCE_FILE_KB = 5120;

    public const MAX_EVIDENCE_TOTAL_KB = 25600;

    public const MAX_COMMENT_LENGTH = 2000;

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
            'description' => ['required', 'string', 'min:20', 'max:'.self::MAX_COMMENT_LENGTH],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            // Optional evidence uploads — additive only (no existing media is deleted).
            'new_images' => ['nullable', 'array', 'max:'.self::MAX_EVIDENCE_FILES],
            'new_images.*' => [ // NOSONAR
                'file', // NOSONAR
                'mimes:jpg,jpeg,png,webp,pdf,txt,doc,docx', // NOSONAR
                'max:'.self::MAX_EVIDENCE_FILE_KB, // NOSONAR
            ],
        ];
    }

    /**
     * Run the functional upload pipeline after Laravel's per-file rules.
     *
     * Replaces the former inline total-size loop with array_reduce (inside
     * UploadValidationPipeline) plus the full suite of functional validators.
     * Guard: skip max_files if Laravel's array 'max' already caught it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var list<UploadedFile> $files */
            $files = array_values(array_filter(
                (array) $this->file('new_images', []),
                fn (mixed $f): bool => $f instanceof UploadedFile,
            ));

            if ($files === []) {
                return;
            }

            $rules = UploadRules::fromConfig('reporter_edit');
            $errors = (new UploadValidationPipeline)->validate($files, $rules);

            foreach ($errors as $error) {
                if ($error->code === 'max_files' && $validator->errors()->has('new_images')) {
                    continue;
                }
                $validator->errors()->add('new_images', $error->message);
            }
        });
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
            'new_images' => 'imágenes',
            'new_images.*' => 'imagen',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_images.*.uploaded' => 'No se pudo subir una imagen. Verifica que el archivo no supere 5 MB e inténtalo nuevamente.',
            'new_images.*.file' => 'No se pudo procesar uno de los archivos.',
            'new_images.*.mimes' => 'Formato no permitido. Usa JPG, PNG, WebP, PDF, TXT, Word.',
            'new_images.*.max' => 'Cada imagen no puede superar los 5 MB.',
            'new_images.max' => 'Puedes subir un máximo de '.self::MAX_EVIDENCE_FILES.' imágenes por vez.',
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
