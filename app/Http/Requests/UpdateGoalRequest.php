<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGoalRequest extends FormRequest
{
    /**
     * The route already runs the `update` ability through GoalPolicy.
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query): void {
                    $query->whereNull('user_id')->orWhere('user_id', $this->user()->id);
                }),
            ],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'paused', 'completed', 'abandoned'])],
            'visibility' => ['sometimes', Rule::in(['private', 'group'])],
            'group_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('group_members', 'group_id')->where('user_id', $this->user()->id),
            ],
            'target_start_date' => ['sometimes', 'nullable', 'date'],
            'target_end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:target_start_date'],
        ];
    }

    /**
     * `required_if` cannot express this on a partial update: turning
     * visibility to `group` is only valid if a group is being set *or* one is
     * already on the record. Without the check, a member would get a 200 and
     * a goal that silently stays private, because GoalPolicy's group branch
     * requires a non-null `group_id`.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $goal = $this->route('goal');

            $visibility = $this->input('visibility', $goal?->visibility);

            if ($visibility !== 'group') {
                return;
            }

            $groupId = $this->has('group_id') ? $this->input('group_id') : $goal?->group_id;

            if ($groupId === null) {
                $validator->errors()->add(
                    'group_id',
                    'Choose the group this goal should be visible to.'
                );
            }
        });
    }
}
