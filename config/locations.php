<?php

return [
    'duplicate_detection_enabled' => env('LOCATION_DUPLICATE_DETECTION_ENABLED', true),
    'similarity_threshold' => env('LOCATION_SIMILARITY_THRESHOLD', 0.75),
    'duplicate_active_only' => env('LOCATION_DUPLICATE_ACTIVE_ONLY', true),
];
