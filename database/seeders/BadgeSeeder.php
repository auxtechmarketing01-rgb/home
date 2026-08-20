<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * FR-GAM-03. `key` is what GamificationService matches on, so these four
     * rows must exist for badges to be awardable at all — they are lookup
     * data, not sample data, and are seeded in every environment.
     */
    public function run(): void
    {
        $badges = [
            ['key' => 'streak_7', 'name' => '7-day streak', 'description' => 'Seven consecutive days of focus.', 'icon' => 'fire'],
            ['key' => 'streak_30', 'name' => '30-day streak', 'description' => 'A full month without missing a day.', 'icon' => 'fire'],
            ['key' => 'streak_100', 'name' => '100-day streak', 'description' => 'One hundred consecutive days.', 'icon' => 'trophy'],
            ['key' => 'first_goal_completed', 'name' => 'First goal completed', 'description' => 'Finished a goal from start to end.', 'icon' => 'check-badge'],
        ];

        foreach ($badges as $badge) {
            Badge::query()->updateOrCreate(['key' => $badge['key']], $badge);
        }
    }
}
