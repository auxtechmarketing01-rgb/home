<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This row *is* the running sprint (FR-SPR-03). `started_at` plus
     * `planned_duration_seconds` is the whole timer; nothing client-side is
     * authoritative, which is what lets a session survive the browser being
     * closed entirely.
     *
     * `status` deliberately has no `overtime` value. A sprint past its
     * planned duration is simply "running, past its plan" — a timestamp
     * comparison, not a state to keep in sync (02 §3, FR-SPR-09).
     *
     * The nullable `goal_id`/`roadmap_item_id` use nullOnDelete rather than
     * the default cascade: deleting a roadmap item must not erase the
     * member's logged history of time they actually spent.
     */
    public function up(): void
    {
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('roadmap_item_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('mode', ['pomodoro', 'countdown', 'stopwatch']);
            $table->unsignedInteger('planned_duration_seconds')->nullable();
            $table->unsignedInteger('break_seconds')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            /**
             * Not in 02 §3's original column list, but FR-SPR-04 cannot be
             * implemented without it: excluding paused time from
             * `actual_duration_seconds` requires knowing when the *current*
             * pause began. Deriving it from `updated_at` would break the
             * moment any other column on the row is written.
             */
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('paused_seconds_total')->default(0);
            $table->unsignedInteger('actual_duration_seconds')->nullable();
            $table->enum('status', ['running', 'paused', 'completed', 'cancelled'])->default('running');
            $table->text('notes')->nullable();
            $table->timestamp('notified_expired_at')->nullable();
            $table->timestamps();

            /** Supports the one-active-sprint-per-user check in StartSprintAction. */
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'started_at']);
            $table->index(['goal_id', 'status']);
            $table->index(['roadmap_item_id', 'status']);
            /** Supports NotifyExpiredSprintsJob's per-minute sweep. */
            $table->index(['status', 'notified_expired_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sprints');
    }
};
