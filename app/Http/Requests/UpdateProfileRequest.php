<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-AUTH-04: name, avatar and timezone. `timezone` drives the streak
     * day boundary, so it is validated as a real IANA identifier.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all'],
            'avatar' => ['sometimes', 'nullable', 'image', 'max:4096'],
            'settings' => ['sometimes', 'array'],
            'settings.gamification_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
