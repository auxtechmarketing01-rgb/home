<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-ADM-01, deliberately minimal: a flag and a disable switch, not a
     * role system. The closed-group nature of the app means a handful of
     * protected routes is sufficient, and a `roles` table would be
     * scaffolding for a problem this product does not have.
     *
     * Note this is a genuine role column on `users`, unlike "mentor" — which
     * is a *relationship* and lives in `mentorships` (01 §4.7). Admin really
     * is a property of the account.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('settings');
            $table->timestamp('disabled_at')->nullable()->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'disabled_at']);
        });
    }
};
