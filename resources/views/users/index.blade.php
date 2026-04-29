@extends('layouts.app')

@section('title', 'Gestion de usuarios')

@section('content')
    @php
        $searchValue = (string) ($filters['search'] ?? '');
        $roleValue = (string) ($filters['role'] ?? '');
        $perPageValue = (int) ($filters['per_page'] ?? 15);
        $activeFilterCount = 0;

        if ($searchValue !== '') {
            $activeFilterCount++;
        }

        if ($roleValue !== '') {
            $activeFilterCount++;
        }
    @endphp

    <div class="users-page">
        <section class="panel panel-pad users-hero">
            <div class="users-hero-head">
                <div>
                    <p class="users-overline">Panel de control</p>
                    <h1 class="users-title">Gestion de usuarios y roles</h1>
                    <p class="users-subtitle">Administra identidades con una vista clara, consistente y segura para altas, mantenimiento y control de accesos.</p>
                </div>

                <div class="users-hero-actions">
                    <a href="{{ route('users.create') }}" class="btn-primary">Nuevo usuario</a>
                </div>
            </div>

            <div class="users-kpi-grid">
                <article class="users-kpi-card">
                    <p class="users-kpi-label">Total usuarios</p>
                    <p class="users-kpi-value">{{ number_format($users->total()) }}</p>
                    <p class="users-kpi-note">Registro global de cuentas en la plataforma.</p>
                </article>

                <article class="users-kpi-card">
                    <p class="users-kpi-label">Mostrando</p>
                    <p class="users-kpi-value">{{ number_format($users->count()) }}</p>
                    <p class="users-kpi-note">Resultado visible en esta pagina.</p>
                </article>

                <article class="users-kpi-card">
                    <p class="users-kpi-label">Filtros activos</p>
                    <p class="users-kpi-value">{{ $activeFilterCount }}</p>
                    <p class="users-kpi-note">Busqueda y rol aplicados actualmente.</p>
                </article>
            </div>
        </section>

        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert-error">
                <ul class="users-error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="panel panel-pad">
            <h2 class="users-section-title">Filtros de busqueda</h2>
            <p class="users-section-note">Refina resultados por identidad, rol y densidad de pagina.</p>

            <form method="GET" action="{{ route('users.index') }}">
                <div class="users-filter-grid">
                    <div>
                        <label for="search" class="users-field-label">Busqueda</label>
                        <input
                            id="search"
                            type="text"
                            name="search"
                            value="{{ $searchValue }}"
                            placeholder="Buscar por nombre o email"
                            class="field"
                        >
                    </div>

                    <div>
                        <label for="role" class="users-field-label">Rol</label>
                        <select id="role" name="role" class="field">
                            <option value="">Todos los roles</option>
                            @foreach ($availableRoles as $role)
                                <option value="{{ $role }}" @selected($roleValue === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="per_page" class="users-field-label">Por pagina</label>
                        <select id="per_page" name="per_page" class="field">
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

        <section class="panel overflow-hidden users-desktop">
            <div class="users-dataset-head">
                <p class="users-dataset-count">{{ number_format($users->count()) }} usuarios en la vista actual</p>
                <span class="users-dataset-chip">Pagina {{ $users->currentPage() }} de {{ $users->lastPage() }}</span>
            </div>

            <table>
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $managedUser)
                    @php($primaryRole = $managedUser->roles->pluck('name')->first() ?? 'reporter')
                    <tr>
                        <td>
                            <span class="users-user-name">{{ $managedUser->name }}</span>
                        </td>
                        <td>
                            <span class="users-user-email">{{ $managedUser->email }}</span>
                        </td>
                        <td>
                            <span class="users-role-badge users-role-badge-{{ $primaryRole }}">{{ str_replace('_', ' ', $primaryRole) }}</span>
                        </td>
                        <td>
                            <span class="users-user-email">{{ optional($managedUser->created_at)->format('Y-m-d H:i') }}</span>
                        </td>
                        <td>
                            <div class="users-actions">
                                <a href="{{ route('users.edit', $managedUser) }}" class="btn-secondary">Editar</a>
                                @if (auth()->id() !== $managedUser->id)
                                    <form method="POST" action="{{ route('users.destroy', $managedUser) }}" onsubmit="return confirm('¿Eliminar usuario? Esta accion no se puede deshacer.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="users-btn-danger">Eliminar</button>
                                    </form>
                                @else
                                    <span class="users-muted">Tu usuario</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="users-empty-row">No hay usuarios para mostrar con los filtros actuales.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section class="users-mobile-list">
            <div class="users-dataset-head panel users-mobile-dataset-head">
                <p class="users-dataset-count">{{ number_format($users->count()) }} usuarios visibles</p>
                <span class="users-dataset-chip">P{{ $users->currentPage() }}/{{ $users->lastPage() }}</span>
            </div>

            @forelse ($users as $managedUser)
                @php($primaryRole = $managedUser->roles->pluck('name')->first() ?? 'reporter')
                <article class="users-mobile-card">
                    <div class="users-mobile-top">
                        <div>
                            <p class="users-user-name users-mobile-name">{{ $managedUser->name }}</p>
                            <p class="users-mobile-meta">{{ $managedUser->email }}</p>
                            <p class="users-mobile-date">Creado: {{ optional($managedUser->created_at)->format('Y-m-d H:i') }}</p>
                        </div>

                        <span class="users-role-badge users-role-badge-{{ $primaryRole }}">{{ str_replace('_', ' ', $primaryRole) }}</span>
                    </div>

                    <div class="users-mobile-actions">
                        <a href="{{ route('users.edit', $managedUser) }}" class="btn-secondary">Editar</a>
                        @if (auth()->id() !== $managedUser->id)
                            <form method="POST" action="{{ route('users.destroy', $managedUser) }}" onsubmit="return confirm('¿Eliminar usuario? Esta accion no se puede deshacer.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="users-btn-danger">Eliminar</button>
                            </form>
                        @else
                            <span class="users-muted users-mobile-self">Tu usuario</span>
                        @endif
                    </div>
                </article>
            @empty
                <article class="users-mobile-card">
                    <p class="users-mobile-empty">No hay usuarios para mostrar con los filtros actuales.</p>
                </article>
            @endforelse
        </section>

        <div class="users-pagination">
            {{ $users->links() }}
        </div>
    </div>
@endsection
