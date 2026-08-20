<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RespondToRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-RWD-03/07. `accepted` is explicit rather than inferred from two
     * separate routes, because a denial carries an optional note and an
     * acceptance does not — one endpoint keeps that asymmetry in one place.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'accepted' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
