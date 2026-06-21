<?php

declare(strict_types=1);

return [
    'media' => [
        /*
         * Hosts allowed for remote media fetching (SSRF allowlist).
         * Populated from SUPABASE_URL at boot; empty in local dev → no remote fetch.
         */
        'remote_allowed_hosts' => array_values(array_filter([
            (string) parse_url((string) env('SUPABASE_URL', ''), PHP_URL_HOST),
        ])),

        'supabase_public_bucket' => env('SUPABASE_PUBLIC_BUCKET', 'tickets'),

        'thumbnail_disk' => env('COMMUNITY_THUMBNAIL_DISK', 'public'),

        'thumbnail_path' => 'community-thumbnails',

        'thumbnail_width' => 640,

        'thumbnail_height' => 360,

        'max_remote_bytes' => 8 * 1024 * 1024,

        'remote_timeout_seconds' => 5,

        'cache_max_age' => 300,
    ],
];
