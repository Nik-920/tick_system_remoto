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
        'similarity_threshold' => floatval(env('TICKET_DEDUP_SIMILARITY_THRESHOLD', 0.90)),
        'observation_threshold' => floatval(env('TICKET_DEDUP_OBSERVATION_THRESHOLD', 0.82)),
        'title_overlap_min_tokens' => intval(env('TICKET_DEDUP_TITLE_OVERLAP_MIN_TOKENS', 1)),
        'window_hours' => intval(env('TICKET_DEDUP_WINDOW_HOURS', 24)),

        // ── Strategy Pattern scoring engine ─────────────────────────────────
        // Weights and thresholds for each DuplicateDetectionStrategy.
        // The score_threshold controls when the Strategy engine considers a
        // candidate a duplicate (parallel scoring — does not replace the
        // legacy isStrongDuplicate() gate in DetectDuplicates).
        'score_threshold' => intval(env('TICKET_DEDUP_STRATEGY_SCORE_THRESHOLD', 70)),

        'strategies' => [
            'embedding_similarity' => [
                'enabled' => true,
                'weight_high' => 50,
                'weight_medium' => 35,
                'weight_low' => 20,
                'high_threshold' => 0.90,
                'medium_threshold' => 0.85,
                'low_threshold' => 0.80,
            ],
            'title_overlap' => [
                'enabled' => true,
                'weight_high' => 25,
                'weight_medium' => 15,
            ],
            'same_location' => [
                'enabled' => true,
                'weight' => 25,
                'different_penalty' => -30,
            ],
            'same_category' => [
                'enabled' => true,
                'weight' => 15,
                'different_penalty' => -10,
            ],
            'time_window' => [
                'enabled' => true,
                'within_24h' => 25,
                'within_72h' => 15,
                'within_7d' => 5,
                'older_than_30d_penalty' => -20,
            ],
            'candidate_state' => [
                'enabled' => true,
                'open' => 20,
                'assigned' => 15,
                'in_progress' => 10,
                'resolved_recent' => 5,
                'cancelled' => -30,
                'rejected' => -20,
            ],
            'recurrence_guard' => [
                'enabled' => true,
                'min_days_for_recurrence' => 30,
            ],
            'generic_text_penalty' => [
                'enabled' => true,
                'min_useful_words' => 4,
                'penalty' => -20,
            ],
            'active_assignment' => [
                'enabled' => true,
                'assigned_bonus' => 15,
                'locked_bonus' => 15,
            ],
            'contextual_duplicate' => [
                'enabled' => true,
                'same_location_category_24h_bonus' => 35,
            ],
            'vision_evidence' => [
                'enabled' => false, // Phase 3 — disabled until vision AI is integrated
            ],
            'historical_recurrence' => [
                'enabled' => true, // Phase 3 — no-op until history table exists
            ],
            'location_incident_pattern' => [
                'enabled' => true, // Phase 3 — no-op until pattern data exists
            ],
        ],
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
