<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default member timezone
    |--------------------------------------------------------------------------
    |
    | Applied to `users.timezone` when a member does not choose one at
    | registration. This is a display/day-boundary preference and is entirely
    | separate from `app.timezone`, which must remain UTC because it decides
    | how timestamps are *stored*.
    |
    */
    'default_timezone' => env('PATHFORGE_DEFAULT_TIMEZONE', 'Asia/Dhaka'),

    /*
    |--------------------------------------------------------------------------
    | Sprints
    |--------------------------------------------------------------------------
    |
    | `stale_grace_hours` is the crash-recovery window used by
    | CleanupStaleSprintsJob. It is deliberately long: reaching the planned
    | duration must never end a sprint, only the user may (FR-SPR-09).
    |
    */
    'sprints' => [
        'stale_grace_hours' => (int) env('PATHFORGE_SPRINT_STALE_GRACE_HOURS', 24),
        'max_planned_duration_seconds' => (int) env('PATHFORGE_SPRINT_MAX_PLANNED_SECONDS', 14400),
        'default_pomodoro_work_seconds' => 1500,
        'default_pomodoro_break_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Projection (FR-ANL-02)
    |--------------------------------------------------------------------------
    |
    | Below `minimum_data_points` distinct active days inside the trailing
    | window, ProjectionService returns null rather than a fabricated date.
    |
    */
    'projection' => [
        'trailing_days' => (int) env('PATHFORGE_PROJECTION_TRAILING_DAYS', 14),
        'minimum_data_points' => (int) env('PATHFORGE_PROJECTION_MIN_DATA_POINTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Leaderboard cache (02 §7)
    |--------------------------------------------------------------------------
    |
    | `store` is null to use the default cache store; set PATHFORGE_CACHE_STORE
    | to `redis` in deploy. Swapping the store stays a config change.
    |
    */
    'leaderboard' => [
        /**
         * `?: null` rather than a bare env() call: an env line written as
         * `PATHFORGE_CACHE_STORE=` yields an empty *string*, and
         * `Cache::store('')` throws "Cache store [] is not defined" — null is
         * what selects the default store.
         */
        'store' => env('PATHFORGE_CACHE_STORE') ?: null,
        'ttl_seconds' => (int) env('PATHFORGE_LEADERBOARD_TTL_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource uploads (02 §8)
    |--------------------------------------------------------------------------
    |
    | Both the extension allow-list and the MIME allow-list are enforced, plus
    | a finfo byte sniff, so a renamed executable is rejected. Zip is excluded
    | on purpose.
    |
    */
    'uploads' => [
        'disk' => env('PATHFORGE_UPLOAD_DISK', env('FILESYSTEM_DISK', 'local')),
        'directory' => env('PATHFORGE_UPLOAD_DIRECTORY', 'resources'),
        'max_size_kilobytes' => (int) env('PATHFORGE_UPLOAD_MAX_KB', 25600),
        'allowed_mime_types' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'text/plain',
            'text/csv',
            'text/markdown',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'allowed_extensions' => [
            'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt', 'csv', 'md',
            'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx',
        ],

        /*
        | Avatars are a narrower case than attachments: images only, and much
        | smaller. They go through the same extension + byte-sniff gates,
        | just with a tighter list.
        */
        'avatar_max_size_kilobytes' => (int) env('PATHFORGE_AVATAR_MAX_KB', 4096),
        'allowed_avatar_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'allowed_avatar_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ],

        /*
        | A sniffed container type is accepted only when the declared
        | extension is one of the formats that genuinely *is* that container:
        | Office documents are zip archives (docx/pptx/xlsx) or OLE2 compound
        | files (doc/xls/ppt), so many libmagic builds report the container
        | rather than the document. Without this, real Office uploads fail;
        | with it, a bare `.zip` is still rejected.
        */
        'container_mime_types' => [
            'application/zip' => ['docx', 'pptx', 'xlsx'],
            'application/CDFV2' => ['doc', 'xls', 'ppt'],
            'application/x-ole-storage' => ['doc', 'xls', 'ppt'],
            'application/vnd.ms-office' => ['doc', 'xls', 'ppt'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rewards (FR-RWD-05, 02 §6)
    |--------------------------------------------------------------------------
    */
    'rewards' => [
        'claim_reminder_grace_days' => (int) env('PATHFORGE_REWARD_CLAIM_GRACE_DAYS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Streaks and gamification (FR-GAM-01/02/03)
    |--------------------------------------------------------------------------
    */
    'streaks' => [
        'reminder_hour' => (int) env('PATHFORGE_STREAK_REMINDER_HOUR', 20),
    ],

    'gamification' => [
        'xp_per_focus_minute' => (int) env('PATHFORGE_XP_PER_FOCUS_MINUTE', 1),
        'xp_per_roadmap_item' => (int) env('PATHFORGE_XP_PER_ROADMAP_ITEM', 25),
        'xp_per_level' => (int) env('PATHFORGE_XP_PER_LEVEL', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Groups
    |--------------------------------------------------------------------------
    */
    'groups' => [
        'invite_code_length' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Horizon access
    |--------------------------------------------------------------------------
    |
    | Comma-separated emails allowed to open the queue dashboard outside the
    | `local` environment. Empty means nobody, so an un-configured production
    | deployment cannot expose it by accident.
    |
    */
    'horizon' => [
        'allowed_emails' => env('PATHFORGE_HORIZON_EMAILS', ''),
        'notify_email' => env('PATHFORGE_HORIZON_NOTIFY_EMAIL'),
    ],

];
