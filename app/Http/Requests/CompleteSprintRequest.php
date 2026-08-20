<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteSprintRequest extends FormRequest
{
    /**
     * The route runs the `update` ability through SprintPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The duration is never accepted from the client: it is computed from
     * `started_at`, `paused_seconds_total` and the server clock, because the
     * server row is the sprint (FR-SPR-03).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
