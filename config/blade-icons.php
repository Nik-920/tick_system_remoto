<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Icons Sets
    |--------------------------------------------------------------------------
    |
    | Additional icon sets beyond those auto-registered by installed packages.
    |
    | IMPORTANT: Do NOT add the "lucide" set here.
    | It is registered by MallardDuck\LucideIcons\BladeLucideIconsServiceProvider
    | via callAfterResolving. Adding it here too causes a prefix collision:
    |   CannotRegisterIconSet: The prefix for "lucide" collides with "lucide" set.
    |
    */

    'sets' => [
        /*
         * We register the lucide set here (instead of relying on the ServiceProvider's
         * auto-discovery) because BladeLucideIconsServiceProvider passes the path as
         * __DIR__.'/../resources/svg', which resolves to "src/../resources/svg".
         * On Docker/Windows volumes, RecursiveDirectoryIterator with a ".." component
         * in the path silently skips ~800 SVG files, breaking icon registration.
         * Using a normalized relative path (no "..") avoids the issue entirely.
         * The ServiceProvider is excluded from discovery via composer.json dont-discover.
         */
        'lucide' => [
            'prefix' => 'lucide',
            'path' => 'vendor/mallardduck/blade-lucide-icons/resources/svg',
        ],
    ],

    'class' => '',

    'attributes' => [],

    'fallback' => '',

    'components' => [
        'disabled' => false,
        'default' => 'icon',
    ],

];
