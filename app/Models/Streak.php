<?php

namespace App\Models;

use Database\Factories\StreakFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The per-member streak (FR-GAM-01), as opposed to the per-goal streak
 * columns on `goal_stats`. Written only by DailyStreakCheckJob, so nothing is
 * mass-assignable.
 */
class Streak extends Model
{
    /** @use HasFactory<StreakFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'current_streak' => 0,
        'longest_streak' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_active_date' => 'immutable_date',
            'last_at_risk_notified_on' => 'immutable_date',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
