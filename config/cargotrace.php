<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Delay rules (group-specific parameters live in .env)
    |--------------------------------------------------------------------------
    | delay = reference arrival - expected arrival
    |   delay <= grace_minutes                 => on time
    |   grace < delay <= low_max_hours         => LOW
    |   low_max_hours < delay <= medium_max_hours => MEDIUM
    |   delay > medium_max_hours               => HIGH
    */
    'delay' => [
        'low_max_hours' => (int) env('DELAY_LOW_MAX_HOURS', 6),
        'medium_max_hours' => (int) env('DELAY_MEDIUM_MAX_HOURS', 24),
        'grace_minutes' => (int) env('DELAY_GRACE_MINUTES', 0),
    ],
];
