<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

    /** Max number of evidence files per request. */
    private const MAX_FILES = 5;

    /**
     * Max size in kilobytes (10 MiB per file) — mirrors StoreTicketRequest.
     * Intentionally bounded: together with MAX_FILES it caps a single request
     * at 50 MiB of evidence, preventing resource-exhaustion uploads.
     */
    private const MAX_FILE_KB = 10240;

    /** @var list<string> */
    private const MEDIA_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'mp4'];

    /** @var list<string> */
    private const MEDIA_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'video/mp4',
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
            'comment' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'evidence.*' => [
                'file',
                // 10 MiB per file and max 5 files per request (50 MiB cap);
                // intentionally bounded for ticket evidence uploads and tested.
                'max:'.self::MAX_FILE_KB, // NOSONAR — safe, explicit content length limit.
                'mimes:'.implode(',', self::MEDIA_EXTENSIONS),
                'mimetypes:'.implode(',', self::MEDIA_MIME_TYPES),
            ],
        ];
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
            'evidence.max' => 'Puedes subir un máximo de '.self::MAX_FILES.' archivos por vez.',
            'evidence.*.max' => 'Cada archivo no puede superar los 10 MB.',
            'evidence.*.mimes' => 'Formato no permitido. Usa JPG, PNG, WebP, PDF, Word, Excel o MP4.',
        ];
    }
}
