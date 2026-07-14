<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-asignación por responsable de ubicación
    |--------------------------------------------------------------------------
    | Cuando está activo y la ubicación del ticket tiene un Jefe de Práctica
    | (locations.responsible_user_id), el ticket recién creado se asigna
    | automáticamente a ese usuario (assignment_source = location_auto) y se
    | dispara TicketAssigned (notificaciones estándar al asignado). Apagado por
    | defecto: sin la env el comportamiento actual no cambia en absoluto.
    */
    'auto_assign_by_location' => (bool) env('TICKET_AUTO_ASSIGN_BY_LOCATION', false),

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
