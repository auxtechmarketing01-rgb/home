<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoalRequest extends FormRequest
{
    /**
     * The route already runs the `create` ability through GoalPolicy.
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query): void {
                    $query->whereNull('user_id')->orWhere('user_id', $this->user()->id);
                }),
            ],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'paused', 'completed', 'abandoned'])],
            'visibility' => ['sometimes', Rule::in(['private', 'group'])],
            /**
             * FR-GOAL-02. Scoped to groups the acting member actually belongs
             * to — otherwise a member could publish a goal into a circle they
             * are not part of, and every member of it would start seeing
             * their data.
             */
            'group_id' => [
                'nullable',
                'required_if:visibility,group',
                'integer',
                Rule::exists('group_members', 'group_id')->where('user_id', $this->user()->id),
            ],
            'target_start_date' => ['nullable', 'date'],
            'target_end_date' => ['nullable', 'date', 'after_or_equal:target_start_date'],
        ];
    }
}
