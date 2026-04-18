@extends('layouts.app')

@section('title', 'Categorias')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Categorias</h1>
                <p class="text-sm text-slate-600 mt-1">Catalogo de clasificacion para incidencias y tickets.</p>
            </div>
            <a href="{{ route('categories.create') }}" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Nueva categoria</a>
        </header>

        @if (session('status'))
            <div class="alert-success bg-green-100 border border-green-300 text-green-800 p-3 rounded-md">{{ session('status') }}</div>
        @endif

        <form method="GET" action="{{ route('categories.index') }}" class="panel panel-pad grid grid-cols-1 md:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar por nombre o descripcion" class="field md:col-span-3">
            <button type="submit" class="btn-primary bg-slate-900 text-white px-3 py-2 rounded-md">Filtrar</button>
        </form>

        <section class="panel overflow-hidden">
            <table>
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Icono</th>
                    <th>Incidencias</th>
                    <th>Tickets</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->icon ?? 'N/A' }}</td>
                        <td>{{ $category->incident_history_count }}</td>
                        <td>{{ $category->tickets_count }}</td>
                        <td>
                            <a href="{{ route('categories.edit', $category) }}" class="text-blue-600 hover:underline">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-slate-600">No hay categorias registradas.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <div>
            {{ $categories->links() }}
        </div>
    </div>
@endsection
