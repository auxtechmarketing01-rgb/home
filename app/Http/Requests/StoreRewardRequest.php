<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRewardRequest extends FormRequest
{
    /**
     * The route runs `create` against the Mentorship, so only the mentor on
     * an `accepted` relationship gets this far.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-RWD-01.
     *
     * The `required_without` pair is where "a reward must be tied to at least
     * one of `goal_id`/`roadmap_item_id`" is enforced. It lives here rather
     * than as a database constraint because "at least one of two nullable
     * columns" is not cleanly expressible as a single Laravel migration
     * constraint (02 §3).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isMonetary = $this->input('type') === 'monetary';

        return [
            'mentorship_id' => ['required', 'integer', 'exists:mentorships,id'],
            'goal_id' => ['nullable', 'required_without:roadmap_item_id', 'integer', 'exists:goals,id'],
            'roadmap_item_id' => ['nullable', 'required_without:goal_id', 'integer', 'exists:roadmap_items,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(['monetary', 'privilege', 'custom'])],
            /**
             * Only meaningful for a monetary reward, so it is prohibited
             * otherwise rather than silently dropped — a "movie night" with
             * an amount attached is a confused record, and this app is a
             * ledger where records matter.
             */
            'monetary_amount' => [
                $isMonetary ? 'nullable' : 'prohibited',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            /**
             * Free text on purpose, not an ISO currency enum: a great many
             * real rewards are labelled "BDT", "USD" or nothing at all
             * (02 §3).
             */
            'currency_label' => [$isMonetary ? 'nullable' : 'prohibited', 'string', 'max:32'],
        ];
    }
}
