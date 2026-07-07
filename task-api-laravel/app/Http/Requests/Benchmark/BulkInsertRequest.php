<?php

namespace App\Http\Requests\Benchmark;

use Illuminate\Foundation\Http\FormRequest;

class BulkInsertRequest extends FormRequest
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
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'count' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
