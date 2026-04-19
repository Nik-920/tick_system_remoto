@extends('layouts.app')

@section('title', 'Nuevo usuario')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Nuevo usuario</h1>
                <p class="text-sm text-slate-600 mt-1">Crear usuario y asignar su rol inicial.</p>
            </div>
            <a href="{{ route('users.index') }}" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Volver</a>
        </header>

        @if ($errors->any())
            <div class="alert-error">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data" class="panel panel-pad space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium mb-1 text-slate-700">Nombre</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="field">
            </div>

            <div>
                <label for="last_name" class="block text-sm font-medium mb-1 text-slate-700">Apellido</label>
                <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required class="field">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium mb-1 text-slate-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="field">
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium mb-1 text-slate-700">Telefono (opcional)</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone') }}" maxlength="30" class="field" placeholder="Ejemplo: +51 999 888 777">
            </div>

            <div>
                <label for="avatar_file" class="block text-sm font-medium mb-1 text-slate-700">Avatar (opcional)</label>
                <input id="avatar_file" name="avatar_file" type="file" accept="image/*" class="field">
                <p class="text-xs text-slate-500 mt-2">Formatos: imagen. Tamano maximo 2 MB.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label for="password" class="block text-sm font-medium mb-1 text-slate-700">Password</label>
                    <input id="password" name="password" type="password" required class="field">
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium mb-1 text-slate-700">Confirmar password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="field">
                </div>
            </div>

            <div>
                <label for="role" class="block text-sm font-medium mb-1 text-slate-700">Rol</label>
                <select id="role" name="role" required class="field">
                    @foreach ($availableRoles as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Crear usuario</button>
            </div>
        </form>
    </div>
@endsection
