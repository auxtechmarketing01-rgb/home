<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRewardRequest extends FormRequest
{
    /**
     * Scoped to the acting member as mentor or mentee (02 §4).
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
            'status' => ['sometimes', Rule::in([
                'requested', 'offered', 'earned', 'claimed', 'fulfilled', 'denied', 'revoked',
            ])],
            /** Which side of the relationship the member is asking about. */
            'role' => ['sometimes', Rule::in(['mentor', 'mentee'])],
            'mentorship_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
