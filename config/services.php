<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
        'role_sync_enabled' => (bool) env('SUPABASE_ROLE_SYNC_ENABLED', false),
        'timeout' => (int) env('SUPABASE_TIMEOUT', 10),
        'auth_admin_users_endpoint' => env('SUPABASE_AUTH_ADMIN_USERS_ENDPOINT', '/auth/v1/admin/users'),
        'storage' => [
            'api_base_url' => env('SUPABASE_STORAGE_API_BASE_URL', env('SUPABASE_URL')),
            'public_base_url' => env('SUPABASE_STORAGE_PUBLIC_BASE_URL', env('SUPABASE_URL')),
            'service_key' => env('SUPABASE_STORAGE_SERVICE_KEY', env('SUPABASE_SERVICE_ROLE_KEY')),
            'timeout' => (int) env('SUPABASE_STORAGE_TIMEOUT', env('SUPABASE_TIMEOUT', 10)),
            'use_local_disk_for_testing' => (bool) env('SUPABASE_STORAGE_USE_LOCAL_DISK_FOR_TESTING', true),
            'testing_disk' => env('SUPABASE_STORAGE_TESTING_DISK', 'public'),
            'domain_buckets' => [
                'categories' => env('SUPABASE_BUCKET_CATEGORIES', 'TicketCategoria'),
                'locations' => env('SUPABASE_BUCKET_LOCATIONS', 'TablaLocations'),
                'tickets' => env('SUPABASE_BUCKET_TICKETS', 'TableTicket'),
                'users' => env('SUPABASE_BUCKET_USERS', 'TablaUsers'),
            ],
            'domain_prefixes' => [
                'categories' => env('SUPABASE_PREFIX_CATEGORIES', 'categories/icons'),
                'locations' => env('SUPABASE_PREFIX_LOCATIONS', 'locations/qr-codes'),
                'tickets' => env('SUPABASE_PREFIX_TICKETS', 'tickets/media'),
                'users' => env('SUPABASE_PREFIX_USERS', 'users/avatars'),
            ],
        ],
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

];
