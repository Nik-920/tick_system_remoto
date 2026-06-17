{{--
  Card: Actividad reciente.
  $activity es un array de eventos (puede estar vacío — TODO: conectar con audit log real).
--}}
@php
$iconColors = [
    'green'  => 'bg-green-50 text-green-600',
    'blue'   => 'bg-blue-50 text-blue-600',
    'orange' => 'bg-orange-50 text-orange-500',
    'red'    => 'bg-red-50 text-red-500',
];
@endphp

<div class="loc-aside-card">
    <h2 class="loc-aside-card__title">Actividad reciente</h2>

    <div class="loc-aside-card__body">
        @if (count($activity) > 0)
            <ul class="loc-activity-list" role="list">
                @foreach ($activity as $item)
                    <li class="loc-activity-item">
                        <span class="loc-activity-item__icon {{ $iconColors[$item['color']] ?? 'bg-gray-50 text-gray-500' }}" aria-hidden="true">
                            @switch($item['icon'])
                                @case('check')
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M20 6 9 17l-5-5" /></svg>
                                    @break
                                @case('pin')
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M12 22s7-5.686 7-12A7 7 0 0 0 5 10c0 6.314 7 12 7 12Z" /><circle cx="12" cy="10" r="2.5" /></svg>
                                    @break
                                @case('clock')
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 3" /></svg>
                                    @break
                                @default
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" /><path d="M12 9v4M12 17h.01" /></svg>
                            @endswitch
                        </span>
                        <div>
                            <p class="loc-activity-item__text">{{ $item['text'] }}</p>
                            <p class="loc-activity-item__time">{{ $item['time'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            {{-- TODO: conectar con audit log real de ubicaciones --}}
            <p class="loc-aside-card__empty">Sin actividad reciente registrada.</p>
        @endif
    </div>
</div>
