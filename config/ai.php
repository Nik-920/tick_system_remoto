<?php

return [
    'enabled' => env('FEATURE_IA_ENABLED', true),

    'huggingface' => [
        'enabled' => env('HUGGINGFACE_ENABLED', true),
        'api_key' => env('HUGGINGFACE_API_KEY'),
        'base_url' => env('HUGGINGFACE_BASE_URL', 'https://router.huggingface.co/hf-inference'),
        'embedding_model' => env('HUGGINGFACE_EMBEDDING_MODEL', 'thenlper/gte-large'),
        'classification_model' => env('HUGGINGFACE_CLASSIFICATION_MODEL', 'facebook/bart-large-mnli'),
        'wait_for_model' => env('HUGGINGFACE_WAIT_FOR_MODEL', true),
        'timeout_seconds' => intval(env('HUGGINGFACE_TIMEOUT_SECONDS', 30)),
        'connect_timeout_seconds' => intval(env('HUGGINGFACE_CONNECT_TIMEOUT_SECONDS', 10)),
        'retries' => intval(env('HUGGINGFACE_RETRIES', 2)),
    ],

    'dedup' => [
        'enabled' => env('FEATURE_SEMANTIC_DEDUP', true),
        'similarity_threshold' => floatval(env('TICKET_DEDUP_SIMILARITY_THRESHOLD', 0.70)),
        'window_hours' => intval(env('TICKET_DEDUP_WINDOW_HOURS', 24)),
    ],

    'recurrence' => [
        'enabled' => env('FEATURE_RECURRENCE_DETECTION', true),
        'window_hours' => intval(env('TICKET_DEDUP_WINDOW_HOURS', 24)),
    ],

    'automation' => [
        'auto_classify' => env('TICKET_AUTO_CLASSIFY', true),
        'auto_priority' => env('TICKET_AUTO_PRIORITY', true),
        'async_processing' => env('TICKET_AI_ASYNC_PROCESSING', true),
    ],
];
