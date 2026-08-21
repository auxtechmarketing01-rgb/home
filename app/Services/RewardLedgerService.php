<?php

namespace App\Services;

use App\Models\Reward;
use App\Models\User;

/**
 * FR-RWD-06: a running per-mentee-per-mentor record of monetary rewards
 * actually delivered, so a parent does not have to remember what they settled.
 *
 * **Not a wallet.** Nothing here is a spendable balance, and only `fulfilled`
 * rewards count — an `earned` or `claimed` one is still a promise, and listing
 * it would read as a debt already paid (01 NFR Financial integrity).
 *
 * Extracted from RewardController, which had grown a grouped aggregate in its
 * body against 02 §4's "controllers stay thin".
 */
class RewardLedgerService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        return Reward::query()
            ->fulfilledMonetary()
            ->forUser($user)
            ->with(['mentorship.mentor', 'mentorship.mentee'])
            ->get()
            ->groupBy('mentorship_id')
            ->map(function ($rewards): array {
                $mentorship = $rewards->first()->mentorship;

                return [
                    'mentorship_id' => $mentorship->id,
                    'mentor' => ['id' => $mentorship->mentor_id, 'name' => $mentorship->mentor?->name],
                    'mentee' => ['id' => $mentorship->mentee_id, 'name' => $mentorship->mentee?->name],
                    'fulfilled_count' => $rewards->count(),
                    /**
                     * Grouped by label rather than summed into one figure:
                     * `currency_label` is free text (02 §3), so adding
                     * "500 BDT" to "20 USD" would produce a meaningless
                     * number.
                     */
                    'totals_by_label' => $rewards
                        ->groupBy(fn (Reward $reward): string => (string) ($reward->currency_label ?? ''))
                        ->map(fn ($group): string => (string) $group->sum(
                            fn (Reward $reward): float => (float) $reward->monetary_amount
                        ))
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }
}
