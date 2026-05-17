<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // OWASP: Autenticación estricta (usando el objeto de la petición para tipado fuerte)
        return $this->user() !== null;
    }

    /**
     * Pre-procesar y sanitizar los datos antes de la validación.
     * OWASP: Sanitización de inputs para evitar XSS persistente.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            // Limpia etiquetas HTML y convierte caracteres especiales para evitar inyecciones XSS
            'title' => $this->title ? htmlspecialchars(strip_tags($this->title), ENT_QUOTES, 'UTF-8') : null,
            'description' => $this->description ? htmlspecialchars(strip_tags($this->description), ENT_QUOTES, 'UTF-8') : null,
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
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],

            // OWASP: Límite estricto en la cantidad de archivos para evitar ataques de DoS por saturación
            'media_files' => ['sometimes', 'array', 'max:5'],

            // OWASP: Validación estricta de archivos (Extensión + MIME Type explícito + Límite de tamaño)
            'media_files.*' => [
                'file',
                'max:10240', // Máximo 10MB para prevenir agotamiento de almacenamiento
                'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,mp4', // Extensiones permitidas
                // Verificación profunda del MIME type real del archivo (evita bypass renombrando extensiones)
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,video/mp4',
            ],
        ];
    }
}
