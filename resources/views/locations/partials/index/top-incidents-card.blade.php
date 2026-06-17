{{-- Card: Top ubicaciones con más incidencias --}}
<div class="loc-aside-card">
    <h2 class="loc-aside-card__title">Top ubicaciones con más incidencias</h2>

    <div class="loc-aside-card__body">
        @if (count($topIncidents) > 0)
            <ul class="loc-top-list" role="list">
                @foreach ($topIncidents as $item)
                    <li class="loc-top-list__item">
                        <span class="loc-top-list__meta">
                            <span class="loc-top-list__code">{{ $item['code'] }}</span>
                            <span class="loc-top-list__name">{{ $item['name'] }}</span>
                        </span>
                        <span class="loc-top-list__count {{ $item['color'] }}">{{ $item['count'] }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="loc-aside-card__empty">Sin incidencias registradas aún.</p>
        @endif
    </div>
</div>
