<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\MentorshipController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\RoadmapItemController;
use App\Http\Controllers\Api\SprintController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| The `api/v1` prefix is applied in bootstrap/app.php (04 Cross-cutting).
| Every route below either runs a Policy ability in its controller or is
| explicitly scoped to the acting user — an absent Policy means self-scoped,
| never unscoped (02 §4).
|
*/

/*
| Unauthenticated entry points. Throttled per 01 NFR Security.
*/
Route::middleware('throttle:auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
});

/*
| FR-AUTH-01. Clicked from a mail client, so it carries no session — the
| `signed` middleware and the email hash are the authorization.
*/
Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:auth'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
    ->middleware(['auth:sanctum', 'throttle:auth'])
    ->name('verification.send');

/*
| `active` rejects a disabled account on its very next request rather than
| only at its next login (FR-ADM-01).
*/
Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    /*
    | Account
    */
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/user', [AuthController::class, 'me'])->name('auth.me');
    Route::put('/user', [AuthController::class, 'updateProfile'])->name('auth.profile.update');

    /*
    | Notification centre (FR-NOT-01). Scoped to self, not policy-checked:
    | the live counterpart of these rows arrives over the member's private
    | Pusher channel (FR-NOT-03), authorized in routes/channels.php.
    */
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');

    /*
    | Categories (FR-GOAL-05)
    */
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');

    /*
    | Goals
    */
    Route::get('/goals', [GoalController::class, 'index'])->name('goals.index');
    Route::post('/goals', [GoalController::class, 'store'])->name('goals.store');
    Route::get('/goals/{goal}', [GoalController::class, 'show'])->name('goals.show');
    Route::put('/goals/{goal}', [GoalController::class, 'update'])->name('goals.update');
    Route::delete('/goals/{goal}', [GoalController::class, 'destroy'])->name('goals.destroy');
    Route::post('/goals/{goal}/complete', [GoalController::class, 'complete'])->name('goals.complete');

    /*
    | Roadmap items
    */
    Route::get('/roadmaps/{roadmap}/items', [RoadmapItemController::class, 'index'])->name('roadmap-items.index');
    Route::post('/roadmaps/{roadmap}/items', [RoadmapItemController::class, 'store'])->name('roadmap-items.store');
    Route::post('/roadmaps/{roadmap}/items/reorder', [RoadmapItemController::class, 'reorder'])->name('roadmap-items.reorder');
    Route::put('/roadmap-items/{item}', [RoadmapItemController::class, 'update'])->name('roadmap-items.update');
    Route::delete('/roadmap-items/{item}', [RoadmapItemController::class, 'destroy'])->name('roadmap-items.destroy');

    /*
    | Focus sprints. `index`, `active` and `export` are scoped to the acting
    | member; the lifecycle routes run `update` through SprintPolicy, which
    | is owner-only with no exceptions (02 §5).
    */
    Route::get('/sprints', [SprintController::class, 'index'])->name('sprints.index');
    Route::get('/sprints/active', [SprintController::class, 'active'])->name('sprints.active');
    Route::get('/sprints/export', [SprintController::class, 'export'])->name('sprints.export');
    Route::post('/sprints/start', [SprintController::class, 'start'])
        ->middleware('throttle:sprint-start')
        ->name('sprints.start');
    Route::post('/sprints/{sprint}/pause', [SprintController::class, 'pause'])->name('sprints.pause');
    Route::post('/sprints/{sprint}/resume', [SprintController::class, 'resume'])->name('sprints.resume');
    Route::post('/sprints/{sprint}/complete', [SprintController::class, 'complete'])->name('sprints.complete');
    Route::post('/sprints/{sprint}/cancel', [SprintController::class, 'cancel'])->name('sprints.cancel');

    /*
    | Attachments (FR-RES-01/02). `view` on the parent to list, `update` to
    | attach — so read access widens with GoalPolicy in later phases while
    | write access stays with the owner.
    */
    Route::get('/goals/{goal}/resources', [ResourceController::class, 'index'])->name('goals.resources.index');
    Route::post('/goals/{goal}/resources', [ResourceController::class, 'store'])->name('goals.resources.store');
    Route::get('/roadmap-items/{item}/resources', [ResourceController::class, 'indexForItem'])
        ->name('roadmap-items.resources.index');
    Route::post('/roadmap-items/{item}/resources', [ResourceController::class, 'storeForItem'])
        ->name('roadmap-items.resources.store');
    Route::get('/resources/{resource}/download', [ResourceController::class, 'download'])
        ->name('resources.download');
    Route::delete('/resources/{resource}', [ResourceController::class, 'destroy'])->name('resources.destroy');

    /*
    | Web Push registration (FR-SPR-10). Always bound to the acting member.
    */
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->name('push-subscriptions.destroy');

    /*
    | Mentor assignment (FR-MENT-05). A separate route for a separate
    | ability: `assign`, never `update` (FR-MENT-06).
    */
    Route::patch('/roadmap-items/{item}/assign', [RoadmapItemController::class, 'assign'])
        ->name('roadmap-items.assign');

    /*
    | Groups (FR-GRP-01/05). `view` for members, `update` for the owner.
    */
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::post('/groups/join', [GroupController::class, 'join'])->name('groups.join');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::put('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
    Route::post('/groups/{group}/invite', [GroupController::class, 'invite'])->name('groups.invite');
    Route::post('/groups/{group}/invite-code', [GroupController::class, 'regenerateInviteCode'])
        ->name('groups.invite-code.regenerate');
    Route::delete('/groups/{group}/members/{member}', [GroupController::class, 'removeMember'])
        ->name('groups.members.remove');
    Route::post('/groups/{group}/leave', [GroupController::class, 'leave'])->name('groups.leave');

    /*
    | Squad Challenges (FR-GRP-04).
    */
    Route::get('/groups/{group}/challenges', [ChallengeController::class, 'index'])
        ->name('challenges.index');
    Route::post('/groups/{group}/challenges', [ChallengeController::class, 'store'])
        ->name('challenges.store');
    Route::get('/challenges/{challenge}', [ChallengeController::class, 'show'])->name('challenges.show');
    Route::post('/challenges/{challenge}/join', [ChallengeController::class, 'join'])
        ->name('challenges.join');
    Route::post('/challenges/{challenge}/leave', [ChallengeController::class, 'leave'])
        ->name('challenges.leave');
    Route::delete('/challenges/{challenge}', [ChallengeController::class, 'destroy'])
        ->name('challenges.destroy');

    /*
    | Analytics. `goalStats` and `leaderboard` are policy-checked;
    | `overview` is scoped to self (02 §4).
    */
    Route::get('/goals/{goal}/stats', [AnalyticsController::class, 'goalStats'])->name('goals.stats');
    Route::get('/groups/{group}/leaderboard', [AnalyticsController::class, 'leaderboard'])
        ->name('groups.leaderboard');
    Route::get('/groups/{group}/trend', [AnalyticsController::class, 'groupTrend'])
        ->name('groups.trend');
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])
        ->name('analytics.overview');

    /*
    | Mentorships (FR-MENT-01..07).
    */
    Route::get('/mentorships', [MentorshipController::class, 'index'])->name('mentorships.index');
    Route::post('/mentorships', [MentorshipController::class, 'store'])->name('mentorships.store');
    Route::get('/mentorships/dashboard', [MentorshipController::class, 'dashboard'])
        ->name('mentorships.dashboard');
    Route::post('/mentorships/{mentorship}/accept', [MentorshipController::class, 'accept'])
        ->name('mentorships.accept');
    Route::post('/mentorships/{mentorship}/decline', [MentorshipController::class, 'decline'])
        ->name('mentorships.decline');
    Route::post('/mentorships/{mentorship}/end', [MentorshipController::class, 'end'])
        ->name('mentorships.end');

    /*
    | Rewards (FR-RWD-01..07). One route per transition in the state machine,
    | each authorizing its own ability — there is no general "update" here.
    */
    Route::get('/rewards', [RewardController::class, 'index'])->name('rewards.index');
    Route::post('/rewards', [RewardController::class, 'store'])->name('rewards.store');
    Route::get('/rewards/ledger', [RewardController::class, 'ledger'])->name('rewards.ledger');
    Route::post('/rewards/request', [RewardController::class, 'request'])->name('rewards.request');
    Route::post('/rewards/{reward}/respond', [RewardController::class, 'respond'])->name('rewards.respond');
    Route::post('/rewards/{reward}/claim', [RewardController::class, 'claim'])->name('rewards.claim');
    Route::post('/rewards/{reward}/fulfill', [RewardController::class, 'fulfill'])->name('rewards.fulfill');
    Route::post('/rewards/{reward}/revoke', [RewardController::class, 'revoke'])->name('rewards.revoke');

    /*
    | Admin (FR-ADM-01). Deliberately minimal and behind the `admin` gate.
    */
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/users', [AdminController::class, 'users'])->name('users.index');
        Route::get('/groups', [AdminController::class, 'groups'])->name('groups.index');
        Route::post('/users/{user}/disable', [AdminController::class, 'disableUser'])->name('users.disable');
        Route::post('/users/{user}/enable', [AdminController::class, 'enableUser'])->name('users.enable');
    });
});
