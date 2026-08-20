<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * There is deliberately no `exists` rule on the code: JoinGroupAction
     * looks it up so an invalid code and an existing membership get different
     * messages, and so this endpoint cannot be used to probe which codes are
     * real.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invite_code' => ['required', 'string', 'max:64'],
        ];
    }
}
