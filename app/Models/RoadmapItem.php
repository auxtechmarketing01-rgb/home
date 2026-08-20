<?php

namespace App\Models;

use Database\Factories\RoadmapItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RoadmapItem extends Model
{
    /** @use HasFactory<RoadmapItemFactory> */
    use HasFactory;

    /**
     * `time_spent_seconds` is owned by RecalculateGoalStatsJob and the
     * mentor-assignment columns are written only by
     * AssignRoadmapItemAction — none of them are mass-assignable (02 §3).
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'title',
        'description',
        'day_number',
        'scheduled_date',
        'estimated_minutes',
        'status',
        'position',
        'reflection_note',
    ];

    /**
     * Mirrors the column defaults from the migration (02 §3) so a freshly
     * created item reports its real status and zeroed rollup rather than
     * nulls.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'todo',
        'time_spent_seconds' => 0,
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'assigned_due_at' => 'immutable_datetime',
            'assigned_minutes' => 'integer',
            'time_spent_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedByMentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_mentor_id');
    }

    /**
     * @return HasMany<Reward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    /**
     * @return BelongsTo<Roadmap, $this>
     */
    public function roadmap(): BelongsTo
    {
        return $this->belongsTo(Roadmap::class);
    }

    /**
     * @return BelongsTo<RoadmapItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(RoadmapItem::class, 'parent_id');
    }

    /**
     * @return HasMany<RoadmapItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(RoadmapItem::class, 'parent_id');
    }

    /**
     * @return HasMany<Sprint, $this>
     */
    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    /**
     * @return MorphMany<ResourceFile, $this>
     */
    public function resourceFiles(): MorphMany
    {
        return $this->morphMany(ResourceFile::class, 'resourceable');
    }

    /**
     * The Goal this item ultimately belongs to. Policies delegate upward
     * through this (02 §5), so it resolves the relation rather than
     * assuming it was eager-loaded.
     */
    public function resolveGoal(): Goal
    {
        $this->loadMissing('roadmap.goal');

        return $this->roadmap->goal;
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }
}
