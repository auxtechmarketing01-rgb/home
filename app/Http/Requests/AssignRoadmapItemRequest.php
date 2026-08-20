<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignRoadmapItemRequest extends FormRequest
{
    /**
     * The route runs the `assign` ability, which is deliberately a different
     * ability from `update` (FR-MENT-06).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-MENT-05. Note what is absent: `title`, `description`, `status` and
     * `estimated_minutes`. A mentor sets expectations, never content — and
     * `estimated_minutes` in particular is the mentee's own estimate, which
     * the mentor's `assigned_minutes` is allowed to disagree with rather than
     * overwrite (02 §3).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'assigned_due_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasAny(['assigned_minutes', 'assigned_due_at'])) {
                $validator->errors()->add(
                    'assigned_minutes',
                    'Provide a time budget, a due date, or both.'
                );
            }
        });
    }
}
