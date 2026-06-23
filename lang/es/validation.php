<?php

declare(strict_types=1);

return [

    'uploaded' => 'No se pudo subir el archivo. Verifica que no supere el tamaño permitido e inténtalo nuevamente.',
    'mimes' => 'El archivo debe ser de tipo: :values.',
    'mimetypes' => 'El archivo debe tener un formato permitido.',
    'max' => [
        'file' => 'El archivo no debe superar :max kilobytes.',
        'array' => 'No puedes subir más de :max archivos.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
    ],
    'required' => 'El campo :attribute es obligatorio.',
    'file' => 'El campo :attribute debe ser un archivo.',

    'attributes' => [
        'media_files' => 'adjuntos',
        'media_files.*' => 'archivo adjunto',
        'new_images' => 'imágenes',
        'new_images.*' => 'imagen',
        'evidence' => 'evidencias',
        'evidence.*' => 'archivo de evidencia',
        'avatar_file' => 'foto de perfil',
    ],

];
