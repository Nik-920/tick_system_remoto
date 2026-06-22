<?php

declare(strict_types=1);

return [
    'media' => [
        'create' => [
            'max_files' => 5,
            'max_file_size_kb' => 10240,
            'max_file_size_mb' => 10,
            'max_total_size_mb' => 50,
            'max_total_size_kb' => 51200,
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'mp4'],
        ],
        'reporter_edit' => [
            'max_files' => 5,
            'max_file_size_kb' => 5120,
            'max_file_size_mb' => 5,
            'max_total_size_mb' => 25,
            'max_total_size_kb' => 25600,
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'doc', 'docx'],
        ],
        'maintenance' => [
            'max_files' => 5,
            'max_file_size_kb' => 5120,
            'max_file_size_mb' => 5,
            'max_total_size_mb' => 25,
            'max_total_size_kb' => 25600,
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'doc', 'docx'],
        ],
    ],
];
