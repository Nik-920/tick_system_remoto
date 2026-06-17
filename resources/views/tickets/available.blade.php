@extends('layouts.app')

@section('title', 'Tickets disponibles')

@section('content')
@php
$priorityValue = (string) ($filters['priority'] ?? '');
$locationValue = (string) ($filters['location_id'] ?? '');
$categoryValue = (string) ($filters['category_id'] ?? '');
$perPageValue  = (string) ($filters['per_page'] ?? '');
$user = auth()->user();
$isMaintenance = $user && $user->hasRole('maintenance') && ! $user->hasAnyRole(['admin', 'super_admin']);
@endphp

<div class="tickets-page available-ticket-list">
    <section class="tickets-hero">
        <div class="tickets-hero-inner">
            <div>
                <p class="tickets-overline">Cola operativa</p>
                <h1 class="tickets-title">Tickets disponibles para tomar</h1>
                <p class="tickets-subtitle">Selecciona una incidencia abierta y sin responsable para iniciar atención.</p>
            </div>
            @if ($isMaintenance)
                <a href="{{ route('tickets.index', ['assignment' => 'mine']) }}" class="btn-secondary tickets-btn-create">Volver a mis tickets</a>
            @else
                <a href="{{ route('tickets.index') }}" class="btn-secondary tickets-btn-create">Ver todos</a>
            @endif
        </div>
    </section>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <section class="tickets-filters">
        <div class="tickets-filters-header">
            <div>
                <h2 class="tickets-filters-title">Filtros rapidos</h2>
                <p class="tickets-filters-subtitle">Refina por prioridad, ubicacion y categoria.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('tickets.available') }}" class="tickets-filter-form">
            <div class="tickets-filter-grid">
                <div>
                    <label for="priority" class="tickets-field-label">Prioridad</label>
                    <select id="priority" name="priority" class="tickets-field">
                        <option value="">Todas</option>
                        @foreach (['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Critica'] as $value => $label)
                            <option value="{{ $value }}" @selected($priorityValue===$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="location_id" class="tickets-field-label">Ubicacion</label>
                    <select id="location_id" name="location_id" class="tickets-field">
                        <option value="">Todas</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected($locationValue===(string) $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="category_id" class="tickets-field-label">Categoria</label>
                    <select id="category_id" name="category_id" class="tickets-field">
                        <option value="">Todas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($categoryValue===(string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="per_page" class="tickets-field-label">Por página</label>
                    <select id="per_page" name="per_page" class="tickets-field">
                        <option value="">15</option>
                        @foreach ([10, 15, 25, 50] as $option)
                            <option value="{{ $option }}" @selected($perPageValue===(string) $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="tickets-filter-actions">
                    <button type="submit" class="btn-primary">Filtrar</button>
                    <a href="{{ route('tickets.available') }}" class="btn-secondary">Limpiar</a>
                </div>
            </div>
        </form>
    </section>

    <section class="tickets-table-shell">
        <div class="tickets-dataset-head">
            <p class="tickets-dataset-count">{{ number_format($tickets->count()) }} tickets disponibles</p>
            <span class="tickets-dataset-chip">Página {{ $tickets->currentPage() }} de {{ $tickets->lastPage() }}</span>
        </div>

        <div class="table-wrap">
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Ubicacion</th>
                        <th>Categoria</th>
                        <th>Prioridad</th>
                        <th>Antigüedad</th>
                        <th>Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td class="tickets-td-title">{{ $ticket->title }}</td>
                            <td class="tickets-td-meta">{{ $ticket->location?->name ?? 'N/A' }}</td>
                            <td class="tickets-td-meta">{{ $ticket->category?->name ?? 'N/A' }}</td>
                            <td>
                                <span class="ticket-badge ticket-badge--{{ $ticket->priority }}">
                                    {{ ucfirst($ticket->priority) }}
                                </span>
                            </td>
                            <td class="tickets-td-meta">{{ $ticket->created_at?->diffForHumans() ?? 'N/A' }}</td>
                            <td>
                                @can('claim', $ticket)
                                    <form method="POST" action="{{ route('tickets.claim', $ticket) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <button type="submit" class="tickets-link-action">Tomar</button>
                                    </form>
                                @else
                                    <span class="tickets-td-meta">No disponible</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="tickets-empty-cell">
                                <x-empty-state
                                    base-class="empty-state"
                                    title="No hay tickets disponibles en este momento"
                                    note="Cuando un reporte abierto quede sin responsable, aparecerá aquí."
                                    title-class="empty-state__title"
                                    note-class="empty-state__note"
                                >
                                    @if ($isMaintenance)
                                        <a href="{{ route('tickets.index', ['assignment' => 'mine']) }}" class="btn-primary">Volver a mis tickets</a>
                                    @else
                                        <a href="{{ route('tickets.index') }}" class="btn-primary">Ver todos los tickets</a>
                                    @endif
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="c-pagination">
        {{ $tickets->links() }}
    </div>
</div>
@endsection
