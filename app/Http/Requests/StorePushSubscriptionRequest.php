<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The raw `PushSubscription.toJSON()` shape the browser produces, which is
 * also what the webpush package expects (02 §9, 03 §4.1).
 *
 * There is deliberately no user field of any kind: the subscription always
 * binds to the authenticated member, so it is structurally impossible to aim
 * one at somebody else's account and start receiving their notifications.
 */
class StorePushSubscriptionRequest extends FormRequest
{
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
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
