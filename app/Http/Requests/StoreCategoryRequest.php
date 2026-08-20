<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-GOAL-05: user-defined categories sit alongside the seeded global
     * ones, so uniqueness is scoped to the acting user.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->where('user_id', $this->user()->id),
            ],
            'icon' => ['nullable', 'string', 'max:64'],
        ];
    }
}
