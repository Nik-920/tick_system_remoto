<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reporte QR público: formulario mínimo para invitados. El campo "website" es
 * un honeypot anti-bots (oculto por CSS): un humano nunca lo llena.
 */
class StorePublicQrReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ruta pública por diseño; el gate real es el feature flag en el controller.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'uuid', Rule::exists('categories', 'id')],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
            // Honeypot: se acepta cualquier valor a propósito — el controller
            // detecta si viene lleno y responde con un éxito falso sin crear
            // nada (no queremos darle al bot una señal de validación).
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required' => 'Cuéntanos qué ocurrió.',
            'description.min' => 'Describe el problema con un poco más de detalle (mínimo 10 caracteres).',
            'description.max' => 'La descripción es demasiado larga (máximo 1000 caracteres).',
            'category_id.required' => 'Selecciona el tipo de incidencia.',
            'category_id.exists' => 'La categoría seleccionada no es válida.',
            'contact_email.email' => 'El correo de contacto no es válido.',
            'photo.image' => 'El archivo debe ser una imagen.',
            'photo.max' => 'La imagen no debe superar 5 MB.',
        ];
    }
}
