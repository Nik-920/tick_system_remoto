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

    <style>
        .users-page {
            width: min(1120px, 100%);
            margin: 0 auto;
            display: grid;
            gap: 1.2rem;
        }

        .users-hero {
            background: linear-gradient(140deg, #f8fbff 0%, #eef4ff 60%, #f4f7fb 100%);
            border: 1px solid #dce8ff;
        }

        .users-hero-head {
            display: grid;
            gap: 0.9rem;
            align-items: start;
        }

        .users-overline {
            margin: 0;
            font-size: 0.73rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #1e40af;
        }

        .users-title {
            margin: 0.2rem 0 0;
            font-size: clamp(1.7rem, 2.6vw, 2.2rem);
            line-height: 1.15;
            font-weight: 800;
            color: #0f172a;
        }

        .users-subtitle {
            margin: 0.52rem 0 0;
            max-width: 66ch;
            color: #334155;
            font-size: 0.95rem;
        }

        .users-hero-actions {
            display: inline-flex;
        }

        .users-hero-actions .btn-primary {
            min-width: 168px;
            justify-content: center;
            text-align: center;
        }

        .users-kpi-grid {
            margin-top: 1rem;
            display: grid;
            gap: 0.75rem;
            grid-template-columns: 1fr;
        }

        .users-kpi-card {
            border: 1px solid #dbe5f4;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.86);
            padding: 0.78rem 0.84rem;
        }

        .users-kpi-label {
            margin: 0;
            color: #475569;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
        }

        .users-kpi-value {
            margin: 0.36rem 0 0;
            color: #0f172a;
            font-size: 1.55rem;
            line-height: 1;
            font-weight: 800;
        }

        .users-kpi-note {
            margin: 0.3rem 0 0;
            color: #64748b;
            font-size: 0.79rem;
        }

        .users-section-title {
            margin: 0;
            font-size: 1.03rem;
            color: #0f172a;
            font-weight: 700;
        }

        .users-section-note {
            margin: 0.2rem 0 0;
            color: #64748b;
            font-size: 0.85rem;
        }

        .users-filter-grid {
            margin-top: 0.85rem;
            display: grid;
            gap: 0.75rem;
            grid-template-columns: 1fr;
            align-items: end;
        }

        .users-field-label {
            display: block;
            margin-bottom: 0.36rem;
            color: #475569;
            font-size: 0.73rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
        }

        .users-filter-actions {
            display: grid;
            gap: 0.62rem;
            grid-template-columns: 1fr 1fr;
        }

        .users-filter-actions .btn-primary,
        .users-filter-actions .btn-secondary {
            width: 100%;
            text-align: center;
        }

        .users-dataset-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.7rem;
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .users-dataset-count {
            margin: 0;
            font-size: 0.84rem;
            color: #334155;
            font-weight: 600;
        }

        .users-dataset-chip {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            font-size: 0.74rem;
            border-radius: 999px;
            padding: 0.2rem 0.52rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .users-desktop {
            display: none;
        }

        .users-desktop table tbody tr {
            transition: background-color 0.16s ease;
        }

        .users-desktop table tbody tr:hover {
            background: #f8fafc;
        }

        .users-user-name {
            font-weight: 700;
            color: #0f172a;
        }

        .users-user-email {
            color: #334155;
            font-size: 0.86rem;
        }

        .users-role-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.22rem 0.64rem;
            font-size: 0.71rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .users-role-badge-super_admin {
            background: #ede9fe;
            color: #6d28d9;
        }

        .users-role-badge-admin {
            background: #dcfce7;
            color: #166534;
        }

        .users-role-badge-maintenance {
            background: #ffedd5;
            color: #b45309;
        }

        .users-role-badge-reporter {
            background: #e2e8f0;
            color: #334155;
        }

        .users-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.42rem;
        }

        .users-actions .btn-secondary,
        .users-actions .users-btn-danger {
            padding: 0.48rem 0.72rem;
            border-radius: 9px;
            font-size: 0.81rem;
            font-weight: 700;
        }

        .users-btn-danger {
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #b91c1c;
            cursor: pointer;
        }

        .users-btn-danger:hover {
            background: #ffe4e6;
        }

        .users-muted {
            color: #64748b;
            font-size: 0.76rem;
            font-weight: 600;
        }

        .users-mobile-list {
            display: grid;
            gap: 0.8rem;
        }

        .users-mobile-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #ffffff;
            padding: 0.95rem;
            box-shadow: 0 14px 28px -28px rgba(15, 23, 42, 0.72);
        }

        .users-mobile-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.7rem;
        }

        .users-mobile-meta {
            margin-top: 0.18rem;
            color: #475569;
            font-size: 0.86rem;
        }

        .users-mobile-date {
            margin-top: 0.34rem;
            color: #64748b;
            font-size: 0.75rem;
        }

        .users-mobile-actions {
            margin-top: 0.82rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .users-mobile-actions .btn-secondary,
        .users-mobile-actions .users-btn-danger {
            min-width: 105px;
            text-align: center;
        }

        .users-pagination {
            display: flex;
            justify-content: center;
            padding-top: 0.12rem;
        }

        @media (min-width: 820px) {
            .users-hero-head {
                grid-template-columns: 1fr auto;
            }

            .users-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .users-filter-grid {
                grid-template-columns: 2fr minmax(160px, 0.9fr) minmax(130px, 0.55fr) minmax(230px, auto);
            }

            .users-filter-actions {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .users-desktop {
                display: block;
            }

            .users-mobile-list {
                display: none;
            }
        }
    </style>

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
                <ul style="margin: 0; padding-left: 1.1rem; display: grid; gap: 0.24rem;">
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
                        <td colspan="5" style="color: #64748b;">No hay usuarios para mostrar con los filtros actuales.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section class="users-mobile-list">
            <div class="users-dataset-head panel" style="padding: 0.72rem 0.84rem; border-radius: 12px;">
                <p class="users-dataset-count">{{ number_format($users->count()) }} usuarios visibles</p>
                <span class="users-dataset-chip">P{{ $users->currentPage() }}/{{ $users->lastPage() }}</span>
            </div>

            @forelse ($users as $managedUser)
                @php($primaryRole = $managedUser->roles->pluck('name')->first() ?? 'reporter')
                <article class="users-mobile-card">
                    <div class="users-mobile-top">
                        <div>
                            <p class="users-user-name" style="margin: 0;">{{ $managedUser->name }}</p>
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
                            <span class="users-muted" style="padding-top: 0.45rem;">Tu usuario</span>
                        @endif
                    </div>
                </article>
            @empty
                <article class="users-mobile-card">
                    <p style="margin: 0; color: #64748b;">No hay usuarios para mostrar con los filtros actuales.</p>
                </article>
            @endforelse
        </section>

        <div class="users-pagination">
            {{ $users->links() }}
        </div>
    </div>
@endsection
