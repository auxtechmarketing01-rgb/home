<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-RWD-01..07. The state machine in 02 §3:
     *
     *   [*] -> requested : mentee asks for something not pre-offered
     *   [*] -> offered   : mentor pre-commits, tied to a goal or item
     *   requested -> offered | denied
     *   offered   -> earned  (automatic, when the linked item/goal is done)
     *   offered   -> revoked (mentor withdraws before it is earned)
     *   earned    -> claimed (mentee demands payout)
     *   claimed   -> fulfilled (mentor confirms delivery)
     *
     * `mentorship_id` is what authorizes every transition: a reward cannot
     * exist outside a mentorship, so every policy check resolves through this
     * FK rather than trusting a user id on the request.
     *
     * **This table is a bookkeeping ledger, never a wallet** (FR-RWD-05, 01
     * NFR Financial integrity). `monetary_amount` records what was promised
     * so a parent does not have to remember; nothing here is a balance and
     * nothing is ever spent inside the app. `currency_label` is free text on
     * purpose — a great many real rewards are "movie night", not money.
     *
     * `goal_id` and `roadmap_item_id` are both nullable with nullOnDelete:
     * "at least one of the two" is enforced in StoreRewardRequest because a
     * migration cannot express it cleanly, and losing the anchor must not
     * delete a fulfilled reward out of the ledger.
     */
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentorship_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('roadmap_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['monetary', 'privilege', 'custom']);
            $table->decimal('monetary_amount', 10, 2)->nullable();
            $table->string('currency_label')->nullable();
            $table->enum('status', [
                'requested', 'offered', 'earned', 'claimed', 'fulfilled', 'denied', 'revoked',
            ])->default('offered');
            $table->enum('requested_by', ['mentor', 'mentee']);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->text('fulfilled_note')->nullable();
            $table->timestamps();

            $table->index(['mentorship_id', 'status']);
            /** Supports MarkRewardsEarnedForItemAction's lookup. */
            $table->index(['roadmap_item_id', 'status']);
            $table->index(['goal_id', 'status']);
            /** Supports SendRewardClaimReminderJob's daily sweep. */
            $table->index(['status', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
