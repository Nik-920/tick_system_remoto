@extends('layouts.app')

@section('title', 'Ubicaciones')

@section('content')

<div class="locations-index">

    @include('locations.partials.index.header')

    @if (session('status'))
        <div class="alert-success" role="alert">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-error" role="alert">{{ session('error') }}</div>
    @endif

    @include('locations.partials.index.metrics', ['metrics' => $metrics])

    <div class="locations-index__grid">

        <main class="locations-index__main">
            @include('locations.partials.index.filters', [
                'filters' => $filters,
            ])

            @include('locations.partials.index.list', [
                'locations' => $locations,
            ])

            @include('locations.partials.index.pagination', [
                'locations' => $locations,
            ])
        </main>

        <aside class="locations-index__aside">
            @include('locations.partials.index.aside', [
                'qrStats'     => $qrStats,
                'activity'    => $activity,
                'topIncidents'=> $topIncidents,
            ])
        </aside>

    </div>

</div>

@endsection
