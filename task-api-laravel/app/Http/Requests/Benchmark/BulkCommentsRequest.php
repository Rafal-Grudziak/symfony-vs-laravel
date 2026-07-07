<?php

namespace App\Http\Requests\Benchmark;

use Illuminate\Foundation\Http\FormRequest;

class BulkCommentsRequest extends FormRequest
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
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'count' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
