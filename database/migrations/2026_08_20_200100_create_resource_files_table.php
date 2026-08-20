<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The hand-rolled storage decided in 02 §3/§9 —
     * `spatie/laravel-medialibrary` is deliberately not used, so there is
     * exactly one storage path, never two.
     *
     * One table covers all three attachment kinds because they share
     * everything except which columns they populate: `file` uses
     * disk/path/mime_type/size_bytes, `link` uses url, `note` uses body
     * (FR-RES-01, FR-RES-02).
     */
    public function up(): void
    {
        Schema::create('resource_files', function (Blueprint $table) {
            $table->id();
            /** Morphs to Goal or RoadmapItem only. */
            $table->morphs('resourceable');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['file', 'link', 'note']);
            $table->string('title');
            $table->string('url')->nullable();
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_files');
    }
};
