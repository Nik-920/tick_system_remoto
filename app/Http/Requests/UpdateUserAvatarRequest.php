<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar_file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar_file.required' => 'Selecciona una foto para subir.',
            'avatar_file.file' => 'No se pudo procesar el archivo de foto.',
            'avatar_file.image' => 'La foto debe ser una imagen válida.',
            'avatar_file.mimes' => 'Solo se permiten imágenes PNG, JPG o WEBP.',
            'avatar_file.max' => 'La foto no debe superar 2 MB.',
            'avatar_file.uploaded' => 'No se pudo subir la foto. Verifica que el archivo no supere 2 MB.',
        ];
    }
}
