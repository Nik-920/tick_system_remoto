<?php

declare(strict_types=1);

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'El comentario no puede estar vacío.',
            'body.min' => 'El comentario debe tener al menos 2 caracteres.',
            'body.max' => 'El comentario no puede superar los 500 caracteres.',
        ];
    }
}
