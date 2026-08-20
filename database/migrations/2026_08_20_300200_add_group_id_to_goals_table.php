<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Phase 3 half of goal visibility (04 Phase 3). The `visibility` enum
     * already carried its final shape from Phase 1, so only the column is
     * added here — and `GoalPolicy::view` gains a second branch rather than
     * being rewritten.
     *
     * nullOnDelete, not cascade: deleting a Group must never delete its
     * members' goals. A `group`-visible goal whose group is gone falls back
     * to owner-only, because the policy's group branch requires a non-null
     * `group_id` — the safe direction to fail in.
     */
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('category_id')
                ->constrained()->nullOnDelete();

            $table->index(['group_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropIndex(['group_id', 'visibility']);
            $table->dropConstrainedForeignId('group_id');
        });
    }
};
