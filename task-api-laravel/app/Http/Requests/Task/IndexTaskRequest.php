<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTaskRequest extends FormRequest
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
            'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in([
                Task::STATUS_TODO,
                Task::STATUS_IN_PROGRESS,
                Task::STATUS_DONE,
                Task::STATUS_CANCELLED,
            ])],
            'priority' => ['sometimes', 'nullable', 'string', Rule::in([
                Task::PRIORITY_LOW,
                Task::PRIORITY_MEDIUM,
                Task::PRIORITY_HIGH,
                Task::PRIORITY_URGENT,
            ])],
        ];
    }
}
