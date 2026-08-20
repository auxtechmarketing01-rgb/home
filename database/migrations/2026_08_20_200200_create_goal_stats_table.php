<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A materialized cache, not a source of truth (02 §3). Every column here
     * is derivable from sprints and roadmap items; the row exists so a
     * leaderboard across a whole group is a cheap read instead of an
     * aggregate over every member's sprint history (01 NFR Performance).
     *
     * Written only by RecalculateGoalStatsJob. Controllers, Actions and
     * Resources read it and never recompute it live.
     */
    public function up(): void
    {
        Schema::create('goal_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_focus_seconds')->default(0);
            $table->unsignedInteger('sessions_count')->default(0);
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('projected_completion_date')->nullable();
            $table->timestamp('last_recalculated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_stats');
    }
};
