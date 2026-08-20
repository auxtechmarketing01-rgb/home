<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `time_spent_seconds` is a denormalized rollup owned by
     * RecalculateGoalStatsJob — never written by a controller (02 §3).
     *
     * The mentor assignment columns (`assigned_by_mentor_id`,
     * `assigned_minutes`, `assigned_due_at`) arrive in Phase 4.
     */
    public function up(): void
    {
        Schema::create('roadmap_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('roadmap_items')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('day_number')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->enum('status', ['todo', 'in_progress', 'done', 'skipped'])->default('todo');
            $table->unsignedInteger('position')->default(0);
            $table->text('reflection_note')->nullable();
            $table->timestamps();

            $table->index(['roadmap_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_items');
    }
};
