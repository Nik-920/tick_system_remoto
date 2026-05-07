@extends('layouts.app')

@section('title', 'Editar usuario')

@section('content')
    <div class="users-surface max-w-4xl mx-auto space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Editar usuario</h1>
                <p class="text-sm text-slate-600 mt-1">Mantenimiento de usuario {{ $managedUser->name }}.</p>
            </div>
            <a href="{{ route('users.index') }}" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Volver</a>
        </header>

        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert-error">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="panel panel-pad">
            <div class="form-title-row">
                <h2 class="text-lg font-semibold text-slate-900">Avatar</h2>
                <p class="text-sm text-slate-600 mt-1">Actualiza la imagen de perfil del usuario.</p>
            </div>

            <form method="POST" action="{{ route('users.update-avatar', $managedUser) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div class="flex items-start gap-4">
                    <div class="shrink-0">
                        @if (is_string($managedUser->avatar_url) && trim($managedUser->avatar_url) !== '')
                            <img src="{{ $managedUser->avatar_url }}" alt="Avatar actual" class="h-16 w-16 rounded-full object-cover border border-slate-200">
                        @else
                            <div class="h-16 w-16 rounded-full border border-slate-300 bg-slate-100 flex items-center justify-center text-slate-500 text-xs">
                                Sin avatar
                            </div>
                        @endif
                    </div>

                    <div class="flex-1">
                        <label for="avatar_file" class="block text-sm font-medium mb-1 text-slate-700">Nuevo avatar</label>
                        <input id="avatar_file" name="avatar_file" type="file" accept="image/*" required class="field">
                        <p class="text-xs text-slate-500 mt-2">Formatos: imagen. Tamano maximo 2 MB.</p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Actualizar avatar</button>
                </div>
            </form>
        </section>

        <section class="panel panel-pad">
            <div class="form-title-row">
                <h2 class="text-lg font-semibold text-slate-900">Datos basicos</h2>
                <p class="text-sm text-slate-600 mt-1">Actualiza la identidad de acceso del usuario.</p>
            </div>

            <form method="POST" action="{{ route('users.update', $managedUser) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name" class="block text-sm font-medium mb-1 text-slate-700">Nombre</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $managedUser->name) }}" required class="field">
                </div>

                <div>
                    <label for="last_name" class="block text-sm font-medium mb-1 text-slate-700">Apellido</label>
                    <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $managedUser->last_name) }}" required class="field">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium mb-1 text-slate-700">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $managedUser->email) }}" required class="field">
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium mb-1 text-slate-700">Telefono (opcional)</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone', $managedUser->phone) }}" maxlength="30" class="field" placeholder="Ejemplo: +51 999 888 777">
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Guardar datos</button>
                </div>
            </form>
        </section>

        <section class="panel panel-pad">
            <div class="form-title-row">
                <h2 class="text-lg font-semibold text-slate-900">Rol del usuario</h2>
                <p class="text-sm text-slate-600 mt-1">
                    Rol actual:
                    <span class="role-pill role-pill-{{ $currentRole }}">{{ $currentRole }}</span>
                </p>
            </div>

            <form method="POST" action="{{ route('users.update-role', $managedUser) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label for="role" class="block text-sm font-medium mb-1 text-slate-700">Nuevo rol</label>
                    <select id="role" name="role" required class="field">
                        @foreach ($availableRoles as $role)
                            <option value="{{ $role }}" @selected(old('role', $currentRole) === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500 mt-2">El cambio aplica de inmediato sobre permisos web y API.</p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary bg-slate-900 text-white px-4 py-2 rounded-md">Actualizar rol</button>
                </div>
            </form>
        </section>

        @if (auth()->id() !== $managedUser->id)
            <section class="danger-block space-y-3">
                <h2 class="text-lg font-semibold text-red-700">Zona de peligro</h2>
                <p class="text-sm text-slate-700">Eliminar usuario de forma permanente.</p>

                <form method="POST" action="{{ route('users.destroy', $managedUser) }}" onsubmit="return confirm('¿Eliminar usuario? Esta accion no se puede deshacer.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="action-chip action-chip-danger">Eliminar usuario</button>
                </form>
            </section>
        @endif
    </div>
@endsection
