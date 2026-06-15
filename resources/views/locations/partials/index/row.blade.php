{{--
  Row de una ubicación individual.
  Variables esperadas: $location (modelo Location con tickets_count + incident_history_count)
--}}
@php
$qrStatus = (string) ($location->qr_generation_status ?? 'pending');

// Status badges
$statusBadge = $location->is_active
    ? ['label' => 'Activo',   'css' => 'loc-status-badge loc-status-badge--active']
    : ['label' => 'Inactivo', 'css' => 'loc-status-badge loc-status-badge--inactive'];

$qrBadgeMap = [
    'ready'      => ['label' => 'Generado',    'css' => 'loc-qr-badge loc-qr-badge--ready'],
    'pending'    => ['label' => 'Pendiente',   'css' => 'loc-qr-badge loc-qr-badge--pending'],
    'processing' => ['label' => 'Procesando',  'css' => 'loc-qr-badge loc-qr-badge--pending'],
    'failed'     => ['label' => 'Error',       'css' => 'loc-qr-badge loc-qr-badge--failed'],
];
$qrBadge = $qrBadgeMap[$qrStatus] ?? ['label' => 'Sin QR', 'css' => 'loc-qr-badge loc-qr-badge--none'];

// Border accent
$borderMap = [
    'ready'      => 'loc-row--border-green',
    'pending'    => 'loc-row--border-orange',
    'processing' => 'loc-row--border-orange',
    'failed'     => 'loc-row--border-red',
];
$borderClass = $location->is_active
    ? ($borderMap[$qrStatus] ?? 'loc-row--border-blue')
    : 'loc-row--border-gray';

// Icon box
$iconBoxMap = [
    'ready'      => 'loc-row__icon--blue',
    'pending'    => 'loc-row__icon--orange',
    'processing' => 'loc-row__icon--orange',
    'failed'     => 'loc-row__icon--red',
];
$iconClass = $location->is_active
    ? ($iconBoxMap[$qrStatus] ?? 'loc-row__icon--blue')
    : 'loc-row__icon--gray';

// Fecha QR
$qrDate = null;
if ($location->qr_generated_at) {
    $qrDate = $location->qr_generated_at->format('d/m/Y');
}

$incidentCount = (int) ($location->tickets_count ?? 0);
@endphp

<div class="loc-row {{ $borderClass }}">

    {{-- Icono + nombre/código --}}
    <div class="loc-row__identity">
        <div class="loc-row__icon {{ $iconClass }}" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                <path d="M12 22s7-5.686 7-12A7 7 0 0 0 5 10c0 6.314 7 12 7 12Z" />
                <circle cx="12" cy="10" r="2.5" />
            </svg>
        </div>
        <div>
            <p class="loc-row__name">{{ $location->name }}</p>
            <p class="loc-row__code">
                Código: <span class="loc-row__code-value">{{ $location->room_code }}</span>
            </p>
        </div>
    </div>

    {{-- Detalles --}}
    <div class="loc-row__details">
        <div class="loc-row__detail-cell">
            <p class="loc-row__detail-label">Edificio</p>
            <p class="loc-row__detail-value">{{ $location->building }}</p>
        </div>
        <div class="loc-row__detail-cell">
            <p class="loc-row__detail-label">Piso</p>
            <p class="loc-row__detail-value">{{ $location->floor ?? '—' }}</p>
        </div>
        <div class="loc-row__detail-cell">
            <p class="loc-row__detail-label">Estado</p>
            <span class="{{ $statusBadge['css'] }}">{{ $statusBadge['label'] }}</span>
        </div>
        <div class="loc-row__detail-cell">
            <p class="loc-row__detail-label">QR</p>
            <span class="{{ $qrBadge['css'] }}">{{ $qrBadge['label'] }}</span>
            @if ($qrDate)
                <p class="loc-row__qr-date">{{ $qrDate }}</p>
            @elseif ($qrStatus === 'failed' && $location->qr_last_error)
                <p class="loc-row__qr-error">{{ Str::limit((string) $location->qr_last_error, 30) }}</p>
            @endif
        </div>
        <div class="loc-row__detail-cell">
            <p class="loc-row__detail-label">Incidencias</p>
            <p class="loc-row__detail-value font-semibold">{{ $incidentCount }}</p>
        </div>
    </div>

    {{-- Acciones --}}
    <div class="loc-row__actions">
        <a href="{{ route('locations.edit', $location) }}" class="loc-row__btn-edit">
            Editar
        </a>

        {{-- Menú kebab --}}
        <div class="loc-row__menu" data-dropdown>
            <button
                type="button"
                class="loc-row__menu-trigger"
                aria-label="Más acciones para {{ $location->name }}"
                aria-haspopup="true"
                aria-expanded="false"
                data-dropdown-trigger
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                    <circle cx="12" cy="5" r="1.5" />
                    <circle cx="12" cy="12" r="1.5" />
                    <circle cx="12" cy="19" r="1.5" />
                </svg>
            </button>

            <div class="loc-row__dropdown" data-dropdown-menu hidden>
                @if (Route::has('locations.regenerate-qr'))
                    <form method="POST" action="{{ route('locations.regenerate-qr', $location) }}">
                        @csrf
                        <button type="submit" class="loc-row__dropdown-item">
                            Regenerar QR
                        </button>
                    </form>
                @endif

                @can('delete', $location)
                    <form
                        method="POST"
                        action="{{ route('locations.destroy', $location) }}"
                        onsubmit="return confirm('Esta acción eliminará la ubicación de forma permanente. ¿Deseas continuar?');"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="loc-row__dropdown-item loc-row__dropdown-item--danger">
                            Eliminar
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

</div>
