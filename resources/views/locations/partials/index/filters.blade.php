{{-- Buscador, chips y ordenamiento --}}
@php
$searchValue   = (string) ($filters['search']    ?? '');
$buildingValue = (string) ($filters['building']  ?? '');
$floorValue    = (string) ($filters['floor']     ?? '');
$perPageValue  = (int)    ($filters['per_page']  ?? 15);

$isActiveRaw   = $filters['is_active'] ?? null;
$isActiveValue = '';
if ($isActiveRaw === true || $isActiveRaw === 1 || $isActiveRaw === '1') {
    $isActiveValue = '1';
} elseif ($isActiveRaw === false || $isActiveRaw === 0 || $isActiveRaw === '0') {
    $isActiveValue = '0';
}

$qrStatusValue = (string) ($filters['qr_status'] ?? '');

$chips = [
    ['label' => 'Todos',        'key' => null,     'val' => null],
    ['label' => 'Activos',      'key' => 'is_active',  'val' => '1'],
    ['label' => 'Inactivos',    'key' => 'is_active',  'val' => '0'],
    ['label' => 'Con QR',       'key' => 'qr_status',  'val' => 'ready'],
    ['label' => 'Sin QR',       'key' => 'qr_status',  'val' => 'pending'],
    ['label' => 'Con error',    'key' => 'qr_status',  'val' => 'failed'],
];
@endphp

<div class="loc-filter-card">
    <form id="loc-filter-form" method="GET" action="{{ route('locations.index') }}">

        {{-- Fila principal: búsqueda + filtros + orden --}}
        <div class="loc-filter-row">
            {{-- Search --}}
            <div class="loc-filter-search">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="loc-filter-search__icon" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m21 21-4.3-4.3" />
                </svg>
                <input
                    id="loc-search"
                    type="text"
                    name="search"
                    value="{{ $searchValue }}"
                    placeholder="Buscar por nombre, código o edificio…"
                    aria-label="Buscar ubicación"
                    class="loc-filter-search__input"
                />
            </div>

            {{-- Filtros avanzados toggle --}}
            <button
                type="button"
                id="loc-adv-toggle"
                aria-expanded="false"
                aria-controls="loc-adv-panel"
                class="loc-filter-btn"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                    <path d="M4 4h16l-6 8v6l-4 2v-8L4 4Z" />
                </svg>
                Filtros
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 loc-adv-chevron" aria-hidden="true">
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </button>

            {{-- Ordenar por --}}
            <div class="loc-filter-sort">
                <span class="loc-filter-sort__label">Ordenar por:</span>
                <select
                    name="per_page"
                    aria-label="Resultados por página"
                    class="loc-filter-btn"
                    onchange="this.form.submit()"
                >
                    @foreach ([10, 15, 25, 50] as $opt)
                        <option value="{{ $opt }}" @selected($perPageValue === $opt)>{{ $opt }} por página</option>
                    @endforeach
                </select>
            </div>

            {{-- Submit oculto para búsqueda --}}
            <button type="submit" class="sr-only" aria-label="Aplicar búsqueda">Buscar</button>
        </div>

        {{-- Panel avanzado (colapsable) --}}
        <div id="loc-adv-panel" class="loc-adv-panel" hidden>
            <div class="loc-adv-grid">
                <div>
                    <label for="loc-building" class="locs-field-label">Edificio</label>
                    <input id="loc-building" type="text" name="building" value="{{ $buildingValue }}" class="locs-field" placeholder="ej. Ingeniería">
                </div>
                <div>
                    <label for="loc-floor" class="locs-field-label">Piso</label>
                    <input id="loc-floor" type="text" name="floor" value="{{ $floorValue }}" class="locs-field" placeholder="ej. 2">
                </div>
                <div>
                    <label for="loc-is-active" class="locs-field-label">Estado</label>
                    <select id="loc-is-active" name="is_active" class="locs-field">
                        <option value="">Todas</option>
                        <option value="1" @selected($isActiveValue === '1')>Activas</option>
                        <option value="0" @selected($isActiveValue === '0')>Inactivas</option>
                    </select>
                </div>
                <div>
                    <label for="loc-qr-status" class="locs-field-label">Estado QR</label>
                    <select id="loc-qr-status" name="qr_status" class="locs-field">
                        <option value="">Todos</option>
                        <option value="ready"      @selected($qrStatusValue === 'ready')>Generado</option>
                        <option value="pending"    @selected($qrStatusValue === 'pending')>Pendiente</option>
                        <option value="processing" @selected($qrStatusValue === 'processing')>Procesando</option>
                        <option value="failed"     @selected($qrStatusValue === 'failed')>Error</option>
                    </select>
                </div>
                <div class="loc-adv-actions">
                    <button type="submit" class="btn-primary">Filtrar</button>
                    <a href="{{ route('locations.index') }}" class="btn-secondary">Limpiar</a>
                </div>
            </div>
        </div>

        {{-- Chips de acceso rápido --}}
        <div class="loc-chips-row">
            @foreach ($chips as $chip)
                @php
                $isActive = $chip['key'] === null
                    ? ($searchValue === '' && $isActiveValue === '' && $qrStatusValue === '')
                    : (
                        ($chip['key'] === 'is_active'  && $isActiveValue  === $chip['val']) ||
                        ($chip['key'] === 'qr_status'  && $qrStatusValue  === $chip['val'])
                    );
                @endphp
                @if ($chip['key'] === null)
                    <a href="{{ route('locations.index') }}"
                       class="loc-filter-chip {{ $isActive ? 'loc-filter-chip--active' : '' }}"
                    >{{ $chip['label'] }}</a>
                @else
                    <a href="{{ route('locations.index', [$chip['key'] => $chip['val']]) }}"
                       class="loc-filter-chip {{ $isActive ? 'loc-filter-chip--active' : '' }}"
                    >{{ $chip['label'] }}</a>
                @endif
            @endforeach
        </div>

    </form>
</div>
