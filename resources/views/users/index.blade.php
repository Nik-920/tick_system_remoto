@extends('layouts.app')

@section('title', 'Gestión de usuarios')

@section('content')
    @php
        $searchValue = (string) ($filters['search'] ?? '');
        $roleValue   = (string) ($filters['role'] ?? '');
        $perPageValue = (int) ($filters['per_page'] ?? 15);
        $activeFilterCount = 0;
        if ($searchValue !== '') $activeFilterCount++;
        if ($roleValue !== '')   $activeFilterCount++;
    @endphp

    <div class="users-page">

        {{-- HERO --}}
        <section class="users-hero">
            <div class="users-hero-inner">
                <div>
                    <p class="users-overline">Panel de control</p>
                    <h1 class="users-title">Gestión de usuarios</h1>
                    <p class="users-subtitle">Administra identidades con una vista clara, consistente y segura para altas, mantenimiento y control de accesos.</p>
                </div>
                <a href="{{ route('users.create') }}" class="btn-primary users-btn-new">Nuevo usuario</a>
            </div>

            <div class="users-kpi-grid">
                <article class="users-kpi-card">
                    <p class="users-kpi-label">Total usuarios</p>
                    <p class="users-kpi-value">{{ number_format($users->total()) }}</p>
                    <p class="users-kpi-note">Registro global de cuentas</p>
                </article>
                <article class="users-kpi-card">
                    <p class="users-kpi-label">Mostrando</p>
                    <p class="users-kpi-value">{{ number_format($users->count()) }}</p>
                    <p class="users-kpi-note">Resultado visible en esta página</p>
                </article>
                <article class="users-kpi-card">
                    <p class="users-kpi-label">Filtros activos</p>
                    <p class="users-kpi-value">{{ $activeFilterCount }}</p>
                    <p class="users-kpi-note">Búsqueda y rol aplicados</p>
                </article>
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

        {{-- FILTROS --}}
        <section class="users-filters">
            <div class="users-filters-header">
                <div>
                    <h2 class="users-filters-title">Filtros de búsqueda</h2>
                    <p class="users-filters-subtitle">Refina por identidad, rol y densidad de página</p>
                </div>
                <span class="users-filter-badge">{{ $activeFilterCount }} activos</span>
            </div>
            <form method="GET" action="{{ route('users.index') }}">
                <div class="users-filter-grid">
                    <div>
                        <label for="search" class="users-field-label">Búsqueda</label>
                        <input id="search" type="text" name="search" value="{{ $searchValue }}"
                               placeholder="Nombre o email" class="users-field">
                    </div>
                    <div>
                        <label for="role" class="users-field-label">Rol</label>
                        <select id="role" name="role" class="users-field">
                            <option value="">Todos los roles</option>
                            @foreach ($availableRoles as $role)
                                <option value="{{ $role }}" @selected($roleValue === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="per_page" class="users-field-label">Por página</label>
                        <select id="per_page" name="per_page" class="users-field">
                            @foreach ([10, 15, 25, 50] as $option)
                                <option value="{{ $option }}" @selected($perPageValue === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="users-filter-actions">
                        <button type="submit" class="btn-primary">Filtrar</button>
                        <a href="{{ route('users.index') }}" class="btn-secondary">Limpiar</a>
                    </div>
                </div>
            </form>
        </section>

        {{-- TABLA --}}
        <section class="users-table-shell">
            <div class="users-dataset-head">
                <p class="users-dataset-count">{{ number_format($users->count()) }} usuarios en la vista actual</p>
                <span class="users-dataset-chip">Página {{ $users->currentPage() }} de {{ $users->lastPage() }}</span>
            </div>
            <div class="table-wrap">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Creado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $managedUser)
                            @php $primaryRole = $managedUser->roles->pluck('name')->first() ?? 'reporter'; @endphp
                            <tr>
                                <td>
                                    <div class="users-td-user">
                                        <div class="users-td-avatar">
                                            @if (is_string($managedUser->avatar_url) && trim($managedUser->avatar_url) !== '')
                                                <img src="{{ $managedUser->avatar_url }}" alt="{{ $managedUser->name }}" class="users-td-avatar-img">
                                            @else
                                                {{ strtoupper(substr($managedUser->name, 0, 1)) }}
                                            @endif
                                        </div>
                                        <span class="users-td-name">{{ $managedUser->name }}</span>
                                    </div>
                                </td>
                                <td><span class="users-td-email">{{ $managedUser->email }}</span></td>
                                <td><span class="users-role-badge users-role-badge--{{ $primaryRole }}">{{ str_replace('_', ' ', $primaryRole) }}</span></td>
                                <td><span class="users-td-date">{{ optional($managedUser->created_at)->format('d/m/Y H:i') }}</span></td>
                                <td>
                                    <div class="users-actions">
                                        <a href="{{ route('users.edit', $managedUser) }}" class="users-btn-edit">Editar</a>
                                        @if (auth()->id() !== $managedUser->id)
                                            <form method="POST" action="{{ route('users.destroy', $managedUser) }}"
                                                  onsubmit="return confirm('¿Eliminar usuario? Esta acción no se puede deshacer.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="users-btn-delete">Eliminar</button>
                                            </form>
                                        @else
                                            <span class="users-self-badge">Tú</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="users-empty-cell">
                                    <div class="users-empty-state">
                                        <p class="users-empty-title">No hay usuarios para mostrar</p>
                                        <p class="users-empty-note">Prueba ajustar o limpiar los filtros.</p>
                                        <a href="{{ route('users.create') }}" class="btn-primary">Crear usuario</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="users-pagination">{{ $users->links() }}</div>
    </div>
@endsection