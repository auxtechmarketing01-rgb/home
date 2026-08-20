<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartSprintRequest extends FormRequest
{
    /**
     * The route runs the `create` ability. Whether the member actually owns
     * the goal or roadmap item they are logging against is a real
     * authorization question and is answered in StartSprintAction, not here
     * (02 §5).
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
        $isStopwatch = $this->input('mode') === 'stopwatch';

        return [
            'mode' => ['required', Rule::in(['pomodoro', 'countdown', 'stopwatch'])],
            'goal_id' => ['nullable', 'integer', 'exists:goals,id'],
            'roadmap_item_id' => ['nullable', 'integer', 'exists:roadmap_items,id'],
            /**
             * A stopwatch is open-ended by definition (FR-SPR-02), so it has
             * no planned duration to reach and no overtime state.
             */
            'planned_duration_seconds' => [
                $isStopwatch ? 'prohibited' : 'required',
                'nullable',
                'integer',
                'min:30',
                'max:'.(int) config('pathforge.sprints.max_planned_duration_seconds'),
            ],
            'break_seconds' => ['sometimes', 'integer', 'min:0', 'max:3600'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
