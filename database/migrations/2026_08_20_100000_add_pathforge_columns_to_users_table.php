<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends the default users table with the profile and gamification
     * columns from 02 §3. `xp`/`level` stay out of the model's $fillable —
     * only the recalculation job writes them.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('password');
            $table->string('timezone')->default('UTC')->after('avatar_path');
            $table->unsignedInteger('xp')->default(0)->after('timezone');
            $table->unsignedInteger('level')->default(1)->after('xp');
            $table->json('settings')->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'timezone', 'xp', 'level', 'settings']);
        });
    }
};
