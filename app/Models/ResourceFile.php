<?php

namespace App\Models;

use Database\Factories\ResourceFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Named `ResourceFile`, not `Resource`, to keep the domain concept clear of
 * Laravel's `Http\Resources` — flag any reintroduction of `Resource.php`
 * as a model (02 §2 naming note).
 */
class ResourceFile extends Model
{
    /** @use HasFactory<ResourceFileFactory> */
    use HasFactory;

    /**
     * The storage columns (`disk`, `path`, `mime_type`, `size_bytes`) are
     * absent on purpose: they describe where the server actually put the
     * bytes and are set by FileStorageService, never by a request payload.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'title',
        'url',
        'body',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function resourceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isStoredFile(): bool
    {
        return $this->type === 'file' && $this->path !== null;
    }

    /**
     * The Goal or RoadmapItem this attachment hangs off. Policies delegate
     * to whichever it is (02 §5), so this always resolves the relation
     * rather than assuming it was eager-loaded.
     */
    public function resolveParent(): Goal|RoadmapItem
    {
        $this->loadMissing('resourceable');

        return $this->resourceable;
    }
}
