{{-- Card: Estado de generación QR (donut CSS + leyenda) --}}
@php
$cumulative = 0;
$stops = [];
foreach ($qrStats as $stat) {
    $start = $cumulative;
    $cumulative += $stat['pct'];
    $stops[] = "{$stat['color']} {$start}% {$cumulative}%";
}
$donutGradient = implode(', ', $stops);
$total = array_sum(array_column($qrStats, 'value'));
@endphp

<div class="loc-aside-card">
    <h2 class="loc-aside-card__title">Estado de generación QR</h2>

    <div class="loc-donut-wrap" aria-hidden="true">
        <div
            class="loc-donut"
            style="background: conic-gradient({{ $donutGradient }}); mask: radial-gradient(farthest-side, transparent calc(100% - 20px), #fff calc(100% - 20px)); -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 20px), #fff calc(100% - 20px));"
        ></div>
    </div>

    <ul class="loc-donut-legend" role="list">
        @foreach ($qrStats as $stat)
            <li class="loc-donut-legend__item">
                <span class="loc-donut-legend__dot" style="background-color: {{ $stat['color'] }}" aria-hidden="true"></span>
                <span class="loc-donut-legend__label">{{ $stat['label'] }}</span>
                <span class="loc-donut-legend__value">{{ $stat['value'] }} ({{ number_format($stat['pct'], 1) }}%)</span>
            </li>
        @endforeach
    </ul>

    <p class="loc-aside-card__footer">Total: {{ $total }} ubicaciones</p>
</div>
