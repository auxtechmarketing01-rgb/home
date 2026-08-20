<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mentorship is a relationship between two specific people, not a role on
     * a user (01 §4.7). The same person is a mentor to a younger sibling and
     * an ordinary mentee on their own goals, so there is deliberately no
     * `role` column on `users` — a fixed enum there would force an untrue
     * choice per account.
     *
     * The unique pair means a re-request after an `ended` relationship
     * *updates* that row rather than inserting a duplicate, keeping the
     * history of a pair in one place (02 §3).
     *
     * `requested_by_user_id` records who initiated, which is what lets
     * `respond` be restricted to the other party (FR-MENT-02) and lets the UI
     * say "waiting on them" correctly.
     */
    public function up(): void
    {
        Schema::create('mentorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'declined', 'ended'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['mentor_id', 'mentee_id']);
            /** Both sides are queried: "my mentors" and "my mentees". */
            $table->index(['mentee_id', 'status']);
            $table->index(['mentor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorships');
    }
};
