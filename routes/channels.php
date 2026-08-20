<?php

use App\Models\Challenge;
use App\Models\Mentorship;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels
|--------------------------------------------------------------------------
|
| Channel authorization is a privacy boundary, not a convenience: a member who
| can subscribe to another member's channel sees their notifications in real
| time regardless of what any Policy says (01 §5 Privacy). Every channel here
| is private and every callback returns a hard boolean, and each one mirrors
| the Policy that guards the same data over HTTP.
|
| See `02-BACKEND-ARCHITECTURE.md` §10.3 for the full table.
|
*/

/**
 * The channel Laravel's own `broadcast` notification channel targets, so
 * authorizing it is what makes the in-app notification centre live
 * (FR-NOT-01, FR-NOT-03).
 */
Broadcast::channel('App.Models.User.{id}', function (User $user, string $id): bool {
    return $user->id === (int) $id;
});

/**
 * Leaderboard and challenge activity. Mirrors GroupPolicy::view.
 */
Broadcast::channel('groups.{groupId}', function (User $user, string $groupId): bool {
    return $user->belongsToGroup((int) $groupId);
});

/**
 * Mirrors ChallengePolicy::view, which itself defers to the parent group —
 * so a challenge is exactly as subscribable as the group it lives in.
 */
Broadcast::channel('challenges.{challengeId}', function (User $user, string $challengeId): bool {
    $challenge = Challenge::query()->find((int) $challengeId);

    if ($challenge === null) {
        return false;
    }

    return $user->belongsToGroup($challenge->group_id);
});

/**
 * The reward state machine's live updates. Mirrors MentorshipPolicy::view,
 * and additionally requires `accepted` — an `ended` mentorship must stop
 * granting access going forward (FR-MENT-07), and a channel left open after
 * the relationship ended would be a quiet exception to that.
 */
Broadcast::channel('mentorships.{mentorshipId}', function (User $user, string $mentorshipId): bool {
    $mentorship = Mentorship::query()->find((int) $mentorshipId);

    return $mentorship !== null
        && $mentorship->isAccepted()
        && $mentorship->involves($user);
});
