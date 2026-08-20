<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RequestMentorshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `role` is the role the **other** person would take, which is what lets
     * one endpoint serve both directions: either the prospective mentee or
     * the prospective mentor may initiate (02 §3).
     *
     * Note there is no shared-group rule here. That check is real
     * authorization, not input validation, so it belongs to
     * RequestMentorshipAction — and putting it only here would leave it
     * unenforced for any caller that does not pass through this class
     * (02 §5, FR-MENT-01).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', Rule::in(['mentor', 'mentee'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((int) $this->input('user_id') === $this->user()->id) {
                $validator->errors()->add('user_id', 'You cannot mentor yourself.');
            }
        });
    }
}
