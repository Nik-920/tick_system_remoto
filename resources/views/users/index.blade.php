@extends('layouts.app')

@section('title', 'Gestión de usuarios')

@section('content')
<div class="users-page">

    {{-- HERO --}}
    <section class="users-hero">
        <div class="users-hero-inner">
            <div>
                <p class="users-overline">Panel de control</p>
                <h1 class="users-title">Gestión de usuarios</h1>
                <p class="users-subtitle">{{ number_format($users->total()) }} usuarios en el sistema</p>
            </div>
            <a href="{{ route('users.create') }}" class="btn-primary users-btn-new">Nuevo usuario</a>
        </div>
    </section>

    @if (session('status'))
    <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
    <div class="alert-error">
        <ul class="space-y-1">
            @foreach ($errors->all() as $error)<li class="text-sm">{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- SUMMARY CHIPS --}}
    <div class="users-summary">
        <div class="users-summary-chip users-summary-chip--reporter">
            <svg class="users-summary-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="users-summary-count">{{ $roleCounts['reporter'] }}</span>
            <span class="users-summary-label">{{ $roleCounts['reporter'] === 1 ? 'Reporter' : 'Reporters' }}</span>
        </div>
        <div class="users-summary-chip users-summary-chip--admin">
            <svg class="users-summary-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span class="users-summary-count">{{ $roleCounts['admin'] }}</span>
            <span class="users-summary-label">{{ $roleCounts['admin'] === 1 ? 'Admin' : 'Admins' }}</span>
        </div>
        <div class="users-summary-chip users-summary-chip--maintenance">
            <svg class="users-summary-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            <span class="users-summary-count">{{ $roleCounts['maintenance'] }}</span>
            <span class="users-summary-label">{{ $roleCounts['maintenance'] === 1 ? 'Maintenance' : 'Maintenances' }}</span>
        </div>
    </div>

    {{-- SEARCH + ROLE TABS --}}
    <section class="users-toolbar">
        <form method="GET" action="{{ route('users.index') }}" class="users-search-form">
            @if ($roleFilter)
            <input type="hidden" name="role" value="{{ $roleFilter }}">
            @endif
            <input
                type="text"
                name="search"
                value="{{ $searchValue }}"
                placeholder="Buscar por nombre o correo..."
                class="users-search"
                aria-label="Buscar usuarios"
            >
            <button type="submit" class="users-search-btn" aria-label="Buscar">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            </button>
        </form>
        <nav class="users-role-tabs" aria-label="Filtrar por rol">
            <a href="{{ route('users.index', ['search' => $searchValue, 'role' => '']) }}"
               class="users-role-tab {{ $roleFilter === '' ? 'users-role-tab--active' : '' }}">Todos</a>
            @foreach ($availableRoles as $availableRole)
                @if ($availableRole !== 'super_admin')
                <a href="{{ route('users.index', ['search' => $searchValue, 'role' => $availableRole]) }}"
                   class="users-role-tab users-role-tab--{{ str_replace('_', '-', $availableRole) }} {{ $roleFilter === $availableRole ? 'users-role-tab--active' : '' }}">
                    {{ ucwords(str_replace('_', ' ', $availableRole)) }}
                </a>
                @endif
            @endforeach
        </nav>
    </section>

    {{-- GRID DE CARDS --}}
    @if ($users->count())
    <div class="users-grid">
        @foreach ($users as $managedUser)
            @include('users.partials.card', ['managedUser' => $managedUser])
        @endforeach
    </div>

    <div class="c-pagination">{{ $users->links() }}</div>
    @else
    <x-empty-state
        base-class="empty-state"
        title="No se encontraron usuarios"
        note="Prueba ajustar o limpiar los filtros."
        title-class="empty-state__title"
        note-class="empty-state__note"
    >
        @if ($searchValue || $roleFilter)
        <a href="{{ route('users.index') }}" class="btn-secondary">Limpiar filtros</a>
        @else
        <a href="{{ route('users.create') }}" class="btn-primary">Crear usuario</a>
        @endif
    </x-empty-state>
    @endif

</div>
@endsection
