<?php

namespace App\Models;

use Database\Factories\GoalStatsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The per-goal analytics cache. Every column is owned by
 * RecalculateGoalStatsJob, which is why `$fillable` is empty: nothing outside
 * that job may write here, not even an Action (02 §3, §6).
 */
class GoalStats extends Model
{
    /** @use HasFactory<GoalStatsFactory> */
    use HasFactory;

    protected $table = 'goal_stats';

    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'total_focus_seconds' => 0,
        'sessions_count' => 0,
        'completion_percentage' => 0,
        'current_streak' => 0,
        'longest_streak' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completion_percentage' => 'float',
            'projected_completion_date' => 'immutable_date',
            'last_recalculated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }
}
