<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ICS subscription feeds
    |--------------------------------------------------------------------------
    |
    | Subscribing clients (iOS Calendar in particular) choose their own poll
    | cadence and largely ignore Cache-Control for scheduling, so the window
    | below is about keeping the payload small rather than about freshness.
    | The refresh_interval is emitted inside the VCALENDAR as X-PUBLISHED-TTL
    | and REFRESH-INTERVAL, which Outlook honours and iOS treats as a hint.
    |
    */

    'ics' => [
        'past_months' => 3,
        'future_months' => 12,
        'cache_ttl' => 900,
        'refresh_interval' => 'PT15M',
    ],

    /*
    |--------------------------------------------------------------------------
    | Recurrence expansion
    |--------------------------------------------------------------------------
    |
    | Hard caps that bound how much work a single recurring series can cause.
    | Without them an infinite high-frequency rule would expand without limit
    | when a wide window is requested.
    |
    */

    'recurrence' => [
        'max_occurrences_per_window' => 400,
        'max_window_days' => 400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Calendar sync
    |--------------------------------------------------------------------------
    |
    | Disabled by default. The calendar scopes are classified by Google as
    | sensitive, so production use requires OAuth verification; until that is
    | granted the feature stays behind this flag.
    |
    | Push notification channels expire after at most 7 days, so they are
    | renewed ahead of time and polling runs as a safety net for any missed
    | notification (and as the only mechanism in local development, which has
    | no public HTTPS endpoint).
    |
    */

    'google' => [
        'enabled' => env('GOOGLE_SYNC_ENABLED', false),
        'watch_ttl_days' => 7,
        'watch_renew_within_hours' => 24,
        'poll_interval_minutes' => 10,
        'initial_sync_past_months' => 3,
        'max_syncs_per_hour_per_link' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conflict detection
    |--------------------------------------------------------------------------
    */

    'conflicts' => [
        'window_days' => 7,
    ],

];
