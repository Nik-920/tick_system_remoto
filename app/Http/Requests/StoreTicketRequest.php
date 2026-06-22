<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use App\Support\Functional\UploadRules;
use App\Support\Functional\UploadValidationPipeline;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTicketRequest extends FormRequest
{
    private const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    private const MAX_MEDIA_FILES = 5;

    private const MAX_MEDIA_SIZE_KB = 10240;

    private const MEDIA_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'mp4'];

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

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ticket::class) ?? false;
    }

    /**
     * Normalize text inputs to plain text before validation.
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'priority' => ['nullable', Rule::in(self::PRIORITIES)],
            'community_visible' => ['nullable', 'boolean'],
            'media_files' => ['sometimes', 'array', 'max:'.self::MAX_MEDIA_FILES],
            'media_files.*' => [
                'file',
                'max:'.self::MAX_MEDIA_SIZE_KB,
                'mimes:'.implode(',', self::MEDIA_EXTENSIONS),
                'mimetypes:'.implode(',', self::MEDIA_MIME_TYPES),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'titulo',
            'description' => 'descripcion',
            'location_id' => 'ubicacion',
            'category_id' => 'categoria',
            'priority' => 'prioridad',
            'media_files' => 'adjuntos',
            'media_files.*' => 'archivo adjunto',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = self::MAX_MEDIA_SIZE_KB / 1024;

        return [
            'media_files.*.uploaded' => 'No se pudo subir una evidencia. Verifica que el archivo no supere '.$maxMb.' MB e inténtalo nuevamente.',
            'media_files.*.max' => 'Cada evidencia no debe superar '.$maxMb.' MB.',
            'media_files.*.mimes' => 'Solo se permiten archivos JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX o MP4.',
            'media_files.*.mimetypes' => 'Solo se permiten archivos JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX o MP4.',
            'media_files.*.file' => 'No se pudo procesar uno de los archivos adjuntos.',
            'media_files.max' => 'Puedes adjuntar hasta '.self::MAX_MEDIA_FILES.' archivos.',
        ];
    }

    /**
     * Run the functional upload pipeline after Laravel's per-file rules.
     *
     * The pipeline adds total-size validation (not covered by Laravel's 'max'
     * rule, which is per-file only) and provides a unified functional view
     * of all upload constraints for the 'create' profile.
     *
     * Guard: skip max_files if Laravel's array 'max' already caught it
     * (both would add to 'media_files'). All other pipeline errors are
     * additive — they go to the array key while Laravel's per-item errors
     * go to 'media_files.N', so there is no key collision.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var list<UploadedFile> $files */
            $files = array_values(array_filter(
                (array) $this->file('media_files', []),
                fn (mixed $f): bool => $f instanceof UploadedFile,
            ));

            if ($files === []) {
                return;
            }

            $rules = UploadRules::fromConfig('create');
            $errors = (new UploadValidationPipeline)->validate($files, $rules);

            foreach ($errors as $error) {
                if ($error->code === 'max_files' && $validator->errors()->has('media_files')) {
                    continue;
                }
                $validator->errors()->add('media_files', $error->message);
            }
        });
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
