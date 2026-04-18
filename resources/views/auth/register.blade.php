@extends('layouts.auth')

@section('title', 'Registro')

@section('content')
    <div class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Crear cuenta</h1>
            <p class="text-sm text-slate-600">Tu usuario se registrara con el rol reporter por defecto.</p>
        </header>

        @if ($errors->any())
            <div class="alert-error bg-red-100 border border-red-300 text-red-800 p-3 rounded-md">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register.store') }}" class="stack space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium mb-1 text-slate-700">Nombre</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="field w-full border rounded-md p-2">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium mb-1 text-slate-700">Correo</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="field w-full border rounded-md p-2">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium mb-1 text-slate-700">Contrasena</label>
                <input id="password" name="password" type="password" required class="field w-full border rounded-md p-2">
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium mb-1 text-slate-700">Confirmar contrasena</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="field w-full border rounded-md p-2">
            </div>

            <div class="actions flex items-center justify-between pt-2">
                <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">Ya tienes cuenta? Inicia sesion</a>
                <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Registrarme</button>
            </div>
        </form>

        <a href="{{ url('/') }}" class="muted-link inline-block text-sm text-slate-600 hover:underline">Volver al inicio</a>
    </div>
@endsection
