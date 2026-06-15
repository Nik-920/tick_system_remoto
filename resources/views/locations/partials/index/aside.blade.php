{{-- Aside derecho: tres cards de soporte --}}
@include('locations.partials.index.qr-status-card', ['qrStats' => $qrStats])
@include('locations.partials.index.activity-card',   ['activity' => $activity])
@include('locations.partials.index.top-incidents-card', ['topIncidents' => $topIncidents])
