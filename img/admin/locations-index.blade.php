@php
    // ===========================================================
    // MOCK DATA — reemplazar luego por datos reales desde el
    // controller / ViewModel (LocationController@index).
    // ===========================================================

    $metrics = [
        [
            'label' => 'Ubicaciones',
            'sub' => 'Registradas',
            'value' => 124,
            'icon' => 'map-pin',
            'bg' => 'bg-blue-50',
            'fg' => 'text-blue-600',
        ],
        [
            'label' => 'Activas',
            'sub' => '95.2% del total',
            'value' => 118,
            'icon' => 'check',
            'bg' => 'bg-green-50',
            'fg' => 'text-green-600',
        ],
        [
            'label' => 'QR generados',
            'sub' => 'Disponibles',
            'value' => 112,
            'icon' => 'qr',
            'bg' => 'bg-blue-50',
            'fg' => 'text-blue-600',
        ],
        [
            'label' => 'Pendientes',
            'sub' => 'Esperando QR',
            'value' => 8,
            'icon' => 'clock',
            'bg' => 'bg-orange-50',
            'fg' => 'text-orange-500',
        ],
        [
            'label' => 'Error en QR',
            'sub' => 'Requieren revisión',
            'value' => 4,
            'icon' => 'alert',
            'bg' => 'bg-red-50',
            'fg' => 'text-red-500',
        ],
    ];

    $filterChips = ['Todos', 'Laboratorios', 'Aulas', 'Oficinas', 'Activos', 'Con QR', 'Sin QR', 'Con error'];
    $activeChip = 'Todos';

    $locations = [
        [
            'name' => 'Laboratorio de Redes',
            'code' => 'LAB-201',
            'building' => 'Ingeniería',
            'floor' => '2',
            'status' => 'Activo',
            'status_color' => 'green',
            'qr' => 'Generado',
            'qr_color' => 'green',
            'qr_date' => '28/05/2026',
            'qr_extra' => null,
            'incidents' => 12,
            'icon' => 'monitor',
            'icon_color' => 'blue',
            'border_color' => 'blue',
            'action' => ['label' => 'Ver detalle', 'style' => 'outline'],
        ],
        [
            'name' => 'Laboratorio de Hardware',
            'code' => 'LAB-301',
            'building' => 'Tecnología',
            'floor' => '3',
            'status' => 'Activo',
            'status_color' => 'green',
            'qr' => 'Pendiente',
            'qr_color' => 'orange',
            'qr_date' => '-',
            'qr_extra' => null,
            'incidents' => 4,
            'icon' => 'laptop',
            'icon_color' => 'orange',
            'border_color' => 'orange',
            'action' => ['label' => 'Generar QR', 'style' => 'solid'],
        ],
        [
            'name' => 'Oficina de Sistemas',
            'code' => 'OF-101',
            'building' => 'Administrativo',
            'floor' => '1',
            'status' => 'Activo',
            'status_color' => 'green',
            'qr' => 'Error',
            'qr_color' => 'red',
            'qr_date' => null,
            'qr_extra' => 'Token inválido',
            'incidents' => 7,
            'icon' => 'building',
            'icon_color' => 'red',
            'border_color' => 'red',
            'action' => ['label' => 'Reintentar', 'style' => 'outline'],
        ],
        [
            'name' => 'Aula A-204',
            'code' => 'AUL-204',
            'building' => 'Ciencias',
            'floor' => '2',
            'status' => 'Activo',
            'status_color' => 'green',
            'qr' => 'Generado',
            'qr_color' => 'green',
            'qr_date' => '25/05/2026',
            'qr_extra' => null,
            'incidents' => 3,
            'icon' => 'folder',
            'icon_color' => 'blue',
            'border_color' => 'blue',
            'action' => ['label' => 'Ver detalle', 'style' => 'outline'],
        ],
        [
            'name' => 'Sala Audiovisual B',
            'code' => 'SA-02',
            'building' => 'Humanidades',
            'floor' => '0',
            'status' => 'Inactivo',
            'status_color' => 'gray',
            'qr' => 'No aplica',
            'qr_color' => 'gray',
            'qr_date' => null,
            'qr_extra' => null,
            'incidents' => 0,
            'icon' => 'room',
            'icon_color' => 'gray',
            'border_color' => 'gray',
            'action' => ['label' => 'Ver detalle', 'style' => 'outline'],
        ],
        [
            'name' => 'Laboratorio de Química',
            'code' => 'LAB-102',
            'building' => 'Ciencias',
            'floor' => '1',
            'status' => 'Activo',
            'status_color' => 'green',
            'qr' => 'Pendiente',
            'qr_color' => 'orange',
            'qr_date' => '-',
            'qr_extra' => null,
            'incidents' => 6,
            'icon' => 'flask',
            'icon_color' => 'orange',
            'border_color' => 'orange',
            'action' => ['label' => 'Generar QR', 'style' => 'solid'],
        ],
    ];

    $qrStats = [
        ['label' => 'Generados', 'value' => 112, 'pct' => 85.5, 'color' => '#22c55e'],
        ['label' => 'Pendientes', 'value' => 8, 'pct' => 6.1, 'color' => '#f97316'],
        ['label' => 'Error', 'value' => 4, 'pct' => 3.0, 'color' => '#ef4444'],
        ['label' => 'No aplica', 'value' => 0, 'pct' => 0.0, 'color' => '#d1d5db'],
    ];

    // conic-gradient stops calculated from cumulative percentages
    $cumulative = 0;
    $gradientStops = [];
    foreach ($qrStats as $stat) {
        $start = $cumulative;
        $cumulative += $stat['pct'];
        $gradientStops[] = "{$stat['color']} {$start}% {$cumulative}%";
    }
    $donutGradient = implode(', ', $gradientStops);

    $activity = [
        [
            'icon' => 'check',
            'color' => 'green',
            'text' => 'QR generado para LAB-201',
            'time' => 'Hace 15 min',
        ],
        [
            'icon' => 'pin',
            'color' => 'blue',
            'text' => 'Nueva ubicación creada: Aula A-205',
            'time' => 'Hace 1 hora',
        ],
        [
            'icon' => 'clock',
            'color' => 'orange',
            'text' => 'QR regenerado para LAB-301',
            'time' => 'Hace 2 horas',
        ],
        [
            'icon' => 'alert',
            'color' => 'red',
            'text' => 'Error en QR de OF-101',
            'time' => 'Hace 3 horas',
        ],
    ];

    $topIncidents = [
        ['code' => 'LAB-201', 'name' => 'Laboratorio de Redes', 'count' => 18, 'color' => 'text-red-500'],
        ['code' => 'LAB-101', 'name' => 'Laboratorio de Computo', 'count' => 15, 'color' => 'text-orange-500'],
        ['code' => 'LAB-301', 'name' => 'Laboratorio de Hardware', 'count' => 9, 'color' => 'text-orange-500'],
        ['code' => 'AUL-204', 'name' => 'Aula A-204', 'count' => 6, 'color' => 'text-blue-600'],
        ['code' => 'OF-101', 'name' => 'Oficina de Sistemas', 'count' => 5, 'color' => 'text-blue-600'],
    ];

    // helper maps for badge / border color classes (Tailwind no permite
    // construir clases dinámicamente, así que se resuelven aquí)
    $badgeColors = [
        'green' => 'bg-green-50 text-green-700',
        'orange' => 'bg-orange-50 text-orange-600',
        'red' => 'bg-red-50 text-red-600',
        'gray' => 'bg-gray-100 text-gray-500',
        'blue' => 'bg-blue-50 text-blue-700',
    ];

    $borderColors = [
        'blue' => 'border-l-blue-500',
        'orange' => 'border-l-orange-400',
        'red' => 'border-l-red-500',
        'gray' => 'border-l-gray-300',
    ];

    $iconBoxColors = [
        'blue' => 'bg-blue-50 text-blue-600',
        'orange' => 'bg-orange-50 text-orange-500',
        'red' => 'bg-red-50 text-red-500',
        'gray' => 'bg-gray-100 text-gray-400',
    ];

    $activityIconColors = [
        'green' => 'bg-green-50 text-green-600',
        'blue' => 'bg-blue-50 text-blue-600',
        'orange' => 'bg-orange-50 text-orange-500',
        'red' => 'bg-red-50 text-red-500',
    ];
@endphp

{{-- =========================================================
     CONTENIDO CENTRAL — Pestaña "Ubicaciones"
     Pegar dentro de <x-app-layout> ... </x-app-layout>
     No incluye header global, sidebar, ni navegación.
========================================================= --}}

<div class="space-y-6">

    {{-- ============ 1. ENCABEZADO DEL MÓDULO ============ --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Ubicaciones</h1>
            <p class="mt-1 text-sm text-gray-500">
                Gestiona los espacios físicos, laboratorios y aulas del sistema.
            </p>
        </div>

        <button
            type="button"
            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 sm:w-auto"
        >
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white/20">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3">
                    <path d="M12 5v14M5 12h14" />
                </svg>
            </span>
            Nueva ubicación
        </button>
    </div>

    {{-- ============ 2. CARDS DE MÉTRICAS ============ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($metrics as $metric)
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $metric['bg'] }} {{ $metric['fg'] }}">
                        @switch($metric['icon'])
                            @case('map-pin')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    <path d="M12 22s7-5.686 7-12A7 7 0 0 0 5 10c0 6.314 7 12 7 12Z" />
                                    <circle cx="12" cy="10" r="2.5" />
                                </svg>
                                @break
                            @case('check')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    <path d="M20 6 9 17l-5-5" />
                                </svg>
                                @break
                            @case('qr')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    <rect x="3" y="3" width="7" height="7" rx="1" />
                                    <rect x="14" y="3" width="7" height="7" rx="1" />
                                    <rect x="3" y="14" width="7" height="7" rx="1" />
                                    <path d="M14 14h3v3h-3zM18 18h3v3h-3z" />
                                </svg>
                                @break
                            @case('clock')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M12 7v5l3 3" />
                                </svg>
                                @break
                            @case('alert')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                                    <path d="M12 9v4M12 17h.01" />
                                </svg>
                                @break
                        @endswitch
                    </div>
                    <div>
                        <p class="text-2xl font-bold leading-tight text-gray-900">{{ $metric['value'] }}</p>
                        <p class="text-sm font-medium text-gray-700">{{ $metric['label'] }}</p>
                        <p class="text-xs text-gray-400">{{ $metric['sub'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ============ GRID PRINCIPAL (col izq + col der) ============ --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[2fr_340px]">

        {{-- ===================== COLUMNA IZQUIERDA ===================== --}}
        <div class="space-y-4">

            {{-- ---- 3. BÚSQUEDA Y FILTROS ---- --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    {{-- buscador --}}
                    <div class="relative flex-1">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400">
                            <circle cx="11" cy="11" r="7" />
                            <path d="m21 21-4.3-4.3" />
                        </svg>
                        <input
                            type="text"
                            placeholder="Buscar por nombre, código o edificio..."
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-10 pr-4 text-sm text-gray-700 placeholder:text-gray-400 focus:border-blue-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100"
                        />
                    </div>

                    {{-- filtros --}}
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                            <path d="M4 4h16l-6 8v6l-4 2v-8L4 4Z" />
                        </svg>
                        Filtros
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </button>

                    {{-- ordenar por --}}
                    <div class="flex items-center gap-2 lg:ml-auto">
                        <span class="whitespace-nowrap text-sm text-gray-500">Ordenar por:</span>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                        >
                            Más recientes
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- chips --}}
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($filterChips as $chip)
                        @php $isActive = $chip === $activeChip; @endphp
                        <button
                            type="button"
                            class="rounded-full px-4 py-1.5 text-sm font-medium transition
                                {{ $isActive
                                    ? 'bg-blue-600 text-white'
                                    : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}"
                        >
                            {{ $chip }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- ---- 4. LISTADO DE UBICACIONES ---- --}}
            <div class="space-y-3">
                @foreach ($locations as $loc)
                    <div class="flex flex-col gap-4 rounded-2xl border border-gray-100 border-l-4 {{ $borderColors[$loc['border_color']] }} bg-white p-4 shadow-sm transition hover:shadow-md sm:flex-row sm:items-center">

                        {{-- icono + nombre/código --}}
                        <div class="flex items-center gap-3 sm:w-56 sm:shrink-0">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $iconBoxColors[$loc['icon_color']] }}">
                                @switch($loc['icon'])
                                    @case('monitor')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                            <rect x="3" y="4" width="18" height="12" rx="2" />
                                            <path d="M8 20h8M12 16v4" />
                                        </svg>
                                        @break
                                    @case('laptop')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                            <rect x="4" y="4" width="16" height="11" rx="1.5" />
                                            <path d="M2 19h20" />
                                        </svg>
                                        @break
                                    @case('building')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                            <rect x="4" y="2" width="16" height="20" rx="1" />
                                            <path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1" />
                                        </svg>
                                        @break
                                    @case('folder')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                            <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" />
                                        </svg>
                                        @break
                                    @case('room')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                            <rect x="3" y="3" width="18" height="18" rx="2" />
                                            <path d="M3 9h18M9 21V9" />
                                        </svg>
                                        @break
                                    @case('flask')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                            <path d="M9 2v6l-5 9a2 2 0 0 0 2 3h12a2 2 0 0 0 2-3l-5-9V2M9 2h6M8 16h8" />
                                        </svg>
                                        @break
                                @endswitch
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">{{ $loc['name'] }}</p>
                                <p class="text-sm text-gray-400">
                                    Código: <span class="font-medium text-blue-600">{{ $loc['code'] }}</span>
                                </p>
                            </div>
                        </div>

                        {{-- detalles en grid --}}
                        <div class="grid flex-1 grid-cols-2 gap-3 text-sm sm:grid-cols-5 sm:gap-4">
                            <div>
                                <p class="text-xs text-gray-400">Edificio</p>
                                <p class="font-medium text-gray-700">{{ $loc['building'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Piso</p>
                                <p class="font-medium text-gray-700">{{ $loc['floor'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Estado</p>
                                <span class="mt-0.5 inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeColors[$loc['status_color']] }}">
                                    {{ $loc['status'] }}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">QR</p>
                                <span class="mt-0.5 inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeColors[$loc['qr_color']] }}">
                                    {{ $loc['qr'] }}
                                </span>
                                @if ($loc['qr_date'])
                                    <p class="mt-0.5 text-xs text-gray-400">{{ $loc['qr_date'] }}</p>
                                @elseif ($loc['qr_extra'])
                                    <p class="mt-0.5 text-xs text-red-400">{{ $loc['qr_extra'] }}</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Incidencias</p>
                                <p class="font-semibold text-gray-700">{{ $loc['incidents'] }}</p>
                            </div>
                        </div>

                        {{-- acción + menú --}}
                        <div class="flex items-center justify-between gap-2 sm:justify-end sm:gap-3">
                            @if ($loc['action']['style'] === 'solid')
                                <button type="button" class="whitespace-nowrap rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                                    {{ $loc['action']['label'] }}
                                </button>
                            @else
                                <button type="button" class="whitespace-nowrap rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                                    {{ $loc['action']['label'] }}
                                </button>
                            @endif

                            <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl text-gray-400 transition hover:bg-gray-50 hover:text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                    <circle cx="12" cy="5" r="1.5" />
                                    <circle cx="12" cy="12" r="1.5" />
                                    <circle cx="12" cy="19" r="1.5" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ---- 5. FOOTER / PAGINACIÓN ---- --}}
            <div class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:flex-row">
                <p class="text-sm text-gray-500">Mostrando 1 a 6 de 124 ubicaciones</p>

                <div class="flex items-center gap-1">
                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 text-gray-400 transition hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                            <path d="m15 18-6-6 6-6" />
                        </svg>
                    </button>

                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-sm font-semibold text-white">1</button>
                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 text-sm font-medium text-gray-600 transition hover:bg-gray-50">2</button>
                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 text-sm font-medium text-gray-600 transition hover:bg-gray-50">3</button>
                    <span class="flex h-9 w-9 items-center justify-center text-sm text-gray-400">...</span>
                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 text-sm font-medium text-gray-600 transition hover:bg-gray-50">21</button>

                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 text-gray-400 transition hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                            <path d="m9 18 6-6-6-6" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ===================== COLUMNA DERECHA ===================== --}}
        <div class="space-y-4">

            {{-- ---- Card A: Estado de generación QR ---- --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Estado de generación QR</h2>

                <div class="mt-4 flex items-center justify-center">
                    <div
                        class="relative flex h-36 w-36 items-center justify-center rounded-full"
                        style="background: conic-gradient({{ $donutGradient }}); mask: radial-gradient(farthest-side, transparent calc(100% - 20px), #fff calc(100% - 20px));"
                    >
                    </div>
                </div>

                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($qrStats as $stat)
                        <li class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-gray-600">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $stat['color'] }}"></span>
                                {{ $stat['label'] }}
                            </span>
                            <span class="font-medium text-gray-700">{{ $stat['value'] }} ({{ number_format($stat['pct'], 1) }}%)</span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4 border-t border-gray-100 pt-3 text-sm text-gray-500">
                    Total: 124 ubicaciones
                </div>
            </div>

            {{-- ---- Card B: Actividad reciente ---- --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Actividad reciente</h2>

                <ul class="mt-4 space-y-4">
                    @foreach ($activity as $item)
                        <li class="flex items-start gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $activityIconColors[$item['color']] }}">
                                @switch($item['icon'])
                                    @case('check')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                            <path d="M20 6 9 17l-5-5" />
                                        </svg>
                                        @break
                                    @case('pin')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                            <path d="M12 22s7-5.686 7-12A7 7 0 0 0 5 10c0 6.314 7 12 7 12Z" />
                                            <circle cx="12" cy="10" r="2.5" />
                                        </svg>
                                        @break
                                    @case('clock')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                            <circle cx="12" cy="12" r="9" />
                                            <path d="M12 7v5l3 3" />
                                        </svg>
                                        @break
                                    @case('alert')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                                            <path d="M12 9v4M12 17h.01" />
                                        </svg>
                                        @break
                                @endswitch
                            </span>
                            <div class="text-sm">
                                <p class="text-gray-700">{{ $item['text'] }}</p>
                                <p class="text-xs text-gray-400">{{ $item['time'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <button type="button" class="mt-4 w-full rounded-xl border border-gray-200 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    Ver toda la actividad
                </button>
            </div>

            {{-- ---- Card C: Top ubicaciones con más incidencias ---- --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Top ubicaciones con más incidencias</h2>

                <ul class="mt-4 space-y-3">
                    @foreach ($topIncidents as $item)
                        <li class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">
                                <span class="font-medium text-gray-800">{{ $item['code'] }}</span>
                                – {{ $item['name'] }}
                            </span>
                            <span class="font-semibold {{ $item['color'] }}">{{ $item['count'] }}</span>
                        </li>
                    @endforeach
                </ul>

                <button type="button" class="mt-4 w-full rounded-xl bg-blue-600 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                    Ver reporte completo
                </button>
            </div>
        </div>
    </div>
</div>
