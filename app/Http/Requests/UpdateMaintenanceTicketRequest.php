<?php

declare(strict_types=1);

namespace App\Http\Requests;

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

    public const MAX_EVIDENCE_FILES = 5;

    public const MAX_EVIDENCE_FILE_KB = 5120;

    public const MAX_EVIDENCE_TOTAL_KB = 25600;

    public const MAX_COMMENT_LENGTH = 2000;

    /** @var list<string> */
    private const MEDIA_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'doc', 'docx'];

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
        return [
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'priority' => ['nullable', Rule::in(self::PRIORITIES)],
            'comment' => ['nullable', 'string', 'max:'.self::MAX_COMMENT_LENGTH],
            'evidence' => ['nullable', 'array', 'max:'.self::MAX_EVIDENCE_FILES],
            'evidence.*' => [ // NOSONAR
                'file', // NOSONAR
                'max:'.self::MAX_EVIDENCE_FILE_KB, // NOSONAR
                'mimes:'.implode(',', self::MEDIA_EXTENSIONS), // NOSONAR
                'mimetypes:'.implode(',', self::MEDIA_MIME_TYPES), // NOSONAR
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $files = $this->file('evidence');
            if (! is_array($files) || count($files) === 0) {
                return;
            }

            $totalSize = 0;
            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $totalSize += $file->getSize();
                }
            }

            if ($totalSize > (self::MAX_EVIDENCE_TOTAL_KB * 1024)) {
                $validator->errors()->add(
                    'evidence',
                    'El tamaño total de los archivos no puede superar los 25 MB.'
                );
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
        return [
            'evidence.max' => 'Puedes subir un máximo de '.self::MAX_EVIDENCE_FILES.' archivos por vez.',
            'evidence.*.max' => 'Cada archivo no puede superar los 5 MB.',
            'evidence.*.mimes' => 'Formato no permitido. Usa JPG, PNG, WebP, PDF, TXT o Word.',
        ];
    }
}
