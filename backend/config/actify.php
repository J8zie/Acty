<?php

return [

    'api_rate_per_minute' => (int) env('ACTIFY_API_RATE_PER_MINUTE', 120),

    'login_per_minute' => 5,

    'register_per_hour' => 10,

    'guest' => [

        'per_ip_per_hour' => (int) env('ACTIFY_GUEST_PER_IP_PER_HOUR', 3),
        'per_phone_per_day' => (int) env('ACTIFY_GUEST_PER_PHONE_PER_DAY', 2),

        'live_window_minutes' => 10,

        'camera_app_window_minutes' => 3,

        'future_skew_minutes' => 2,

        'rejected_retention_days' => 30,
    ],

    'max_active_tasks' => (int) env('ACTIFY_MAX_ACTIVE_TASKS', 5),

    'images' => [

        'max_kb' => 5120,

        'max_megapixels' => 30,

        'avatar_px' => 512,

        'report_max_side' => 1600,
        'jpeg_quality' => 85,
    ],

    'videos' => [

        'max_mb' => 50,

        'mimes' => ['mp4', 'webm', 'quicktime'],
    ],

    'private_link_minutes' => 15,

    'stats_cache_seconds' => 30,

    'pagination' => [
        'default' => 100,
        'max' => 200,
    ],
];
