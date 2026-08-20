<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squad Challenge (FR-GRP-04), stored explicitly rather than derived.
     *
     * The alternative 04 Phase 3 raises — inferring a challenge from goals
     * that share a group and a title — was rejected as fragile: renaming a
     * goal would silently dissolve the challenge, and there would be nowhere
     * to record that joining is opt-in. A challenge is opt-in and a
     * leaderboard is passive; that difference needs a row to live in.
     */
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->timestamps();

            $table->index(['group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
