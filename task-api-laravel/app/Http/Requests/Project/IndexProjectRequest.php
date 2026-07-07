<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProjectRequest extends FormRequest
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in([
                Project::STATUS_DRAFT,
                Project::STATUS_ACTIVE,
                Project::STATUS_ARCHIVED,
            ])],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
