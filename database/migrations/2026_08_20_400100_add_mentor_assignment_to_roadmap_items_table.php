<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-MENT-05: a mentor sets expectations on a mentee's item — a time
     * budget and a due date — and nothing else.
     *
     * `assigned_minutes` is kept separate from `estimated_minutes` on
     * purpose (02 §3): the mentee's own estimate and the mentor's expectation
     * are allowed to disagree, and that disagreement is useful information
     * rather than a conflict to reconcile. Collapsing them into one column
     * would silently let a mentor overwrite the mentee's plan, which is
     * exactly the boundary FR-MENT-06 draws.
     *
     * A null `assigned_by_mentor_id` means no mentor has touched this item
     * and the mentee's own fields stand unmodified.
     */
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->foreignId('assigned_by_mentor_id')->nullable()->after('reflection_note')
                ->constrained('users')->nullOnDelete();
            $table->unsignedInteger('assigned_minutes')->nullable()->after('assigned_by_mentor_id');
            $table->datetime('assigned_due_at')->nullable()->after('assigned_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by_mentor_id');
            $table->dropColumn(['assigned_minutes', 'assigned_due_at']);
        });
    }
};
