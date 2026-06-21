<?php

declare(strict_types=1);

namespace App\Http\Requests\Community;

use App\Models\CommunityReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityCommentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(CommunityReport::allowedReasons())],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
