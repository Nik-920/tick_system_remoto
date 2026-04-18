<?php

return [
    'frame_options' => env('SECURITY_HEADERS_FRAME_OPTIONS', 'SAMEORIGIN'),

    'content_type_options' => env('SECURITY_HEADERS_CONTENT_TYPE_OPTIONS', 'nosniff'),

    'referrer_policy' => env('SECURITY_HEADERS_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

    'permissions_policy' => env('SECURITY_HEADERS_PERMISSIONS_POLICY', 'camera=(), geolocation=(), microphone=()'),

    'cross_origin_opener_policy' => env('SECURITY_HEADERS_COOP', 'same-origin'),

    'cross_origin_resource_policy' => env('SECURITY_HEADERS_CORP', 'same-origin'),

    'csp_mode' => env('SECURITY_HEADERS_CSP_MODE', 'report-only'),

    'csp' => env(
        'SECURITY_HEADERS_CSP',
        "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https://fonts.bunny.net; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'"
    ),

    'hsts_enabled' => env('SECURITY_HEADERS_ENABLE_HSTS', true),

    'hsts_force' => env('SECURITY_HEADERS_FORCE_HSTS', false),

    'hsts_max_age' => (int) env('SECURITY_HEADERS_HSTS_MAX_AGE', 31536000),

    'hsts_include_subdomains' => env('SECURITY_HEADERS_HSTS_INCLUDE_SUBDOMAINS', true),

    'hsts_preload' => env('SECURITY_HEADERS_HSTS_PRELOAD', false),
];
