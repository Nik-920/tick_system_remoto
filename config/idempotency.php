<?php

return [
    'enabled' => env('IDEMPOTENCY_ENABLED', true),
    'driver' => env('IDEMPOTENCY_DRIVER', 'database'),
    'ttl_seconds' => (int) env('IDEMPOTENCY_TTL_SECONDS', 86400),
    'header' => env('IDEMPOTENCY_HEADER', 'Idempotency-Key'),
    'header_fallback' => env('IDEMPOTENCY_HEADER_FALLBACK', 'X-Idempotency-Key'),
    'allow_missing' => env('IDEMPOTENCY_ALLOW_MISSING', true),
    'allowed_methods' => ['POST', 'PATCH', 'PUT', 'DELETE'],
    'max_key_length' => (int) env('IDEMPOTENCY_MAX_KEY_LENGTH', 128),

    // Redis lock that prevents concurrent requests with the same key from
    // racing into the DB acquire step. PostgreSQL idempotency_keys remains the
    // durable source of truth; this lock only coordinates the hot path.
    'lock_enabled' => (bool) env('IDEMPOTENCY_LOCK_ENABLED', true),
    'lock_ttl' => (int) env('IDEMPOTENCY_LOCK_TTL', 30),
];
