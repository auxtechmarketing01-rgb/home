<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestRewardRequest extends FormRequest
{
    /**
     * The route runs `request` against the Mentorship — mentee side only.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-RWD-03, the mentee-initiated entry point.
     *
     * Unlike StoreRewardRequest there is **no** "at least one of
     * goal_id/roadmap_item_id" rule: an offer is a commitment and needs
     * something concrete to hang off, but a request is a question — "would
     * you consider X?" — and does not have to be anchored yet (05 Phase 4).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isMonetary = $this->input('type') === 'monetary';

        return [
            'mentorship_id' => ['required', 'integer', 'exists:mentorships,id'],
            'goal_id' => ['nullable', 'integer', 'exists:goals,id'],
            'roadmap_item_id' => ['nullable', 'integer', 'exists:roadmap_items,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(['monetary', 'privilege', 'custom'])],
            'monetary_amount' => [
                $isMonetary ? 'nullable' : 'prohibited',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'currency_label' => [$isMonetary ? 'nullable' : 'prohibited', 'string', 'max:32'],
        ];
    }
}
