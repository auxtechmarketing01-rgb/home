<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Group is the app's entire social boundary (01 §3.1, §8): there is no
     * public directory and no global graph, so every cross-member feature —
     * shared goal visibility, the leaderboard, challenges, and even who you
     * may ask to mentor you — is scoped through membership here.
     *
     * `invite_code` is unique and regenerable (FR-GRP-01), which is what
     * lets an owner invalidate a code that has been shared too widely.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('invite_code')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
