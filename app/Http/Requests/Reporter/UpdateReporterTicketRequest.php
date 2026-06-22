<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporter;

use App\Http\Requests\Concerns\ValidatesTicketUploads;
use App\Support\Functional\UploadRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
    use ValidatesTicketUploads;

    /** @var list<string> */
    private const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const MAX_COMMENT_LENGTH = 2000;

    public function authorize(): bool
    {
        // The route is gated by auth + role:reporter and the controller performs
        // the per-ticket policy check. Here we only require an authenticated user.
        return $this->user() !== null;
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
            'description' => ['required', 'string', 'min:20', 'max:'.self::MAX_COMMENT_LENGTH],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            ...$this->uploadFileRules('new_images', 'reporter_edit'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->runUploadPipelineAfter('new_images', 'reporter_edit', $v));
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
        $upload = UploadRules::fromConfig('reporter_edit');

        return [
            'new_images.*.uploaded' => 'No se pudo subir una imagen. Verifica que el archivo no supere '.$upload->maxFileSizeLabel.' e inténtalo nuevamente.',
            'new_images.*.file' => 'No se pudo procesar uno de los archivos.',
            'new_images.*.mimes' => 'Formato no permitido. Usa JPG, PNG, WebP, PDF, TXT, Word.',
            'new_images.*.max' => 'Cada imagen no puede superar los '.$upload->maxFileSizeLabel.'.',
            'new_images.max' => 'Puedes subir un máximo de '.$upload->maxFiles.' imágenes por vez.',
        ];
    }
}
