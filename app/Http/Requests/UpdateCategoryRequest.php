<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($this->categoryId()),
            ],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'icon_file' => ['sometimes', 'nullable', 'file', 'image', 'max:2048'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    private function categoryId(): ?string
    {
        $category = $this->route('category');

        if ($category instanceof Category) {
            return $category->id;
        }

        return is_string($category) ? $category : null;
    }
}
