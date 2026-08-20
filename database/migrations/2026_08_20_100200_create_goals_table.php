<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `group_id` is added in Phase 3 alongside the groups table; the
     * `visibility` enum already carries its final shape (02 §3) so the
     * Phase 3 migration only has to add the column.
     *
     * Archiving a goal sets `deleted_at` — it is never hard-deleted
     * (FR-GOAL-03).
     */
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'abandoned'])->default('active');
            $table->enum('visibility', ['private', 'group'])->default('private');
            $table->date('target_start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
