<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-GRP-04. `goal_id` is the creator's own goal they are racing with;
     * CreateChallengeAction verifies ownership, since racing with somebody
     * else's goal would put their progress on your row.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'goal_id' => ['nullable', 'integer', 'exists:goals,id'],
        ];
    }
}
