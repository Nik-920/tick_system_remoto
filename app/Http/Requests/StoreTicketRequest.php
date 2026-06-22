<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTicketUploads;
use App\Models\Ticket;
use App\Support\Functional\UploadRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTicketRequest extends FormRequest
{
    use ValidatesTicketUploads;

    private const PRIORITIES = ['low', 'medium', 'high', 'critical'];

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
        return $this->user()?->can('create', Ticket::class) ?? false;
    }

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
            'priority' => ['nullable', Rule::in(self::PRIORITIES)],
            'community_visible' => ['nullable', 'boolean'],
            ...$this->uploadFileRules('media_files', 'create', 'sometimes', ['mimetypes:'.implode(',', self::MEDIA_MIME_TYPES)]),
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
        $upload = UploadRules::fromConfig('create');

        return [
            'media_files.*.uploaded' => 'No se pudo subir una evidencia. Verifica que el archivo no supere '.$upload->maxFileSizeLabel.' e inténtalo nuevamente.',
            'media_files.*.max' => 'Cada evidencia no debe superar '.$upload->maxFileSizeLabel.'.',
            'media_files.*.mimes' => 'Solo se permiten archivos JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX o MP4.',
            'media_files.*.mimetypes' => 'Solo se permiten archivos JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX o MP4.',
            'media_files.*.file' => 'No se pudo procesar uno de los archivos adjuntos.',
            'media_files.max' => 'Puedes adjuntar hasta '.$upload->maxFiles.' archivos.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->runUploadPipelineAfter('media_files', 'create', $v));
    }
}
