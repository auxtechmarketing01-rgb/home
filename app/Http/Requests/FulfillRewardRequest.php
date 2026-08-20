<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FulfillRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-RWD-05. `note` is where "paid in cash, Aug 20" goes — the app
     * records that something happened outside it and moves no money itself.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
