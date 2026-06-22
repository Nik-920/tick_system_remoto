<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Functional\UploadRules;
use App\Support\Functional\UploadValidationPipeline;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Limited operational edit performed by Maintenance from tickets.show.
 *
 * Only operational fields are accepted: category_id, priority, an optional
 * technical comment and additive evidence uploads. Reporter-owned fields
 * (title, description, reporter_id, created_at) and assignment/state fields
 * are NOT in the rules, so they can never reach the model via validated()
 * even if a crafted payload includes them.
 *
 * Authorization (assigned maintenance / admin / super_admin, non-terminal
 * ticket) is enforced by TicketPolicy@updateMaintenance in the controller.
 */
class UpdateMaintenanceTicketRequest extends FormRequest
{
    /** @var list<string> */
    private const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const MAX_COMMENT_LENGTH = 2000;

    /** @var list<string> */
    private const MEDIA_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function authorize(): bool
    {
        // Route is auth-gated; the controller performs the per-ticket policy check.
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $upload = UploadRules::fromConfig('maintenance');

        return [
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'priority' => ['nullable', Rule::in(self::PRIORITIES)],
            'comment' => ['nullable', 'string', 'max:'.self::MAX_COMMENT_LENGTH],
            'evidence' => ['nullable', 'array', 'max:'.$upload->maxFiles],
            'evidence.*' => [ // NOSONAR
                'file', // NOSONAR
                'max:'.$upload->maxFileSizeKb, // NOSONAR
                'mimes:'.implode(',', $upload->normalizedExtensions()), // NOSONAR
                'mimetypes:'.implode(',', self::MEDIA_MIME_TYPES), // NOSONAR
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
                (array) $this->file('evidence', []),
                fn (mixed $f): bool => $f instanceof UploadedFile,
            ));

            if ($files === []) {
                return;
            }

            $rules = UploadRules::fromConfig('maintenance');
            $errors = (new UploadValidationPipeline)->validate($files, $rules);

            foreach ($errors as $error) {
                if ($error->code === 'max_files' && $validator->errors()->has('evidence')) {
                    continue;
                }
                $validator->errors()->add('evidence', $error->message);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'categoria',
            'priority' => 'prioridad',
            'comment' => 'comentario',
            'evidence' => 'evidencias',
            'evidence.*' => 'archivo de evidencia',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $upload = UploadRules::fromConfig('maintenance');

        return [
            'evidence.*.uploaded' => 'No se pudo subir un archivo de evidencia. Verifica que no supere '.$upload->maxFileSizeLabel.' e inténtalo nuevamente.',
            'evidence.*.file' => 'No se pudo procesar uno de los archivos de evidencia.',
            'evidence.max' => 'Puedes subir un máximo de '.$upload->maxFiles.' archivos por vez.',
            'evidence.*.max' => 'Cada archivo no puede superar los '.$upload->maxFileSizeLabel.'.',
            'evidence.*.mimes' => 'Formato no permitido. Usa JPG, PNG, WebP, PDF, TXT o Word.',
        ];
    }
}
