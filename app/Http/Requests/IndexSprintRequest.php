<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSprintRequest extends FormRequest
{
    /**
     * No Policy: `/sprints` and `/sprints/export` are scoped to the acting
     * user inside SprintQueryService (02 §4).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-SPR-06 filters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'goal_id' => ['sometimes', 'integer'],
            'roadmap_item_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', Rule::in(['running', 'paused', 'completed', 'cancelled'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
