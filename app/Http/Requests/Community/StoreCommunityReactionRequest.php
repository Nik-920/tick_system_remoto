<?php

declare(strict_types=1);

namespace App\Http\Requests\Community;

use App\Models\CommunityReaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(CommunityReaction::allowedTypes())],
        ];
    }
}
