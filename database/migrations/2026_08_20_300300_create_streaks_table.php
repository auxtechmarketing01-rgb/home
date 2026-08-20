<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The per-*member* streak (FR-GAM-01), distinct from the per-goal streak
     * columns on `goal_stats`.
     *
     * 02 §2 lists a `Streak` model with no matching table in §3 — this fills
     * that gap. Both are needed and neither is derivable from the other: a
     * member's streak spans every goal they own, while a goal's streak is
     * about that one goal, and the leaderboard reports the former.
     *
     * Written only by DailyStreakCheckJob.
     */
    public function up(): void
    {
        Schema::create('streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_active_date')->nullable();
            /**
             * The member's *local* date on which the at-risk reminder was
             * last sent, so an hourly job nags at most once a day (FR-NOT-02).
             */
            $table->date('last_at_risk_notified_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streaks');
    }
};
