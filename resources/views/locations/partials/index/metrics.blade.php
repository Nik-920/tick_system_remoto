{{-- KPI strip — cinco métricas de ubicaciones --}}
<div class="loc-idx-kpi-grid">
    @foreach ($metrics as $metric)
        <div class="loc-kpi-card">
            <div class="loc-kpi-card__icon {{ $metric['bg'] }} {{ $metric['fg'] }}">
                @switch($metric['icon'])
                    @case('map-pin')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                            <path d="M12 22s7-5.686 7-12A7 7 0 0 0 5 10c0 6.314 7 12 7 12Z" />
                            <circle cx="12" cy="10" r="2.5" />
                        </svg>
                        @break
                    @case('check')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5" />
                        </svg>
                        @break
                    @case('qr')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                            <rect x="3" y="3" width="7" height="7" rx="1" />
                            <rect x="14" y="3" width="7" height="7" rx="1" />
                            <rect x="3" y="14" width="7" height="7" rx="1" />
                            <path d="M14 14h3v3h-3zM18 18h3v3h-3z" />
                        </svg>
                        @break
                    @case('clock')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 7v5l3 3" />
                        </svg>
                        @break
                    @case('alert')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                            <path d="M12 9v4M12 17h.01" />
                        </svg>
                        @break
                @endswitch
            </div>
            <div>
                <p class="loc-kpi-card__value">{{ $metric['value'] }}</p>
                <p class="loc-kpi-card__label">{{ $metric['label'] }}</p>
                <p class="loc-kpi-card__sub">{{ $metric['sub'] }}</p>
            </div>
        </div>
    @endforeach
</div>
