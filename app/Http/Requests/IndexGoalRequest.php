<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexGoalRequest extends FormRequest
{
    /**
     * The route runs the `viewAny` ability; the returned rows are narrowed
     * by Goal::visibleTo inside GoalQueryService.
     */
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
            'status' => ['sometimes', Rule::in(['draft', 'active', 'paused', 'completed', 'abandoned'])],
            'visibility' => ['sometimes', Rule::in(['private', 'group'])],
            'category_id' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
