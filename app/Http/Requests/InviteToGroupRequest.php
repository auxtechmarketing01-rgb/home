<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteToGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-GRP-01. The email is optional: an owner may simply want the invite
     * code back to pass along themselves, which is the only way to reach
     * somebody who does not have an account yet (there is no public
     * directory, 01 §8).
     *
     * No `exists` rule, so the response cannot be used to discover which
     * addresses have accounts.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
        ];
    }
}
