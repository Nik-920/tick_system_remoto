@extends('layouts.auth')

@section('title', 'Restablecer contrasena')

@section('content')
    <div class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Restablecer contrasena</h1>
            <p class="text-sm text-slate-600">Define una nueva contrasena para tu cuenta.</p>
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

        <form method="POST" action="{{ route('password.update') }}" class="stack space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="block text-sm font-medium mb-1 text-slate-700">Correo</label>
                <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus class="field w-full border rounded-md p-2">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium mb-1 text-slate-700">Nueva contrasena</label>
                <input id="password" name="password" type="password" required class="field w-full border rounded-md p-2">
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium mb-1 text-slate-700">Confirmar contrasena</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="field w-full border rounded-md p-2">
            </div>

            <div class="actions flex items-center justify-between pt-2">
                <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">Volver a iniciar sesion</a>
                <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Restablecer</button>
            </div>
        </form>

        <a href="{{ url('/') }}" class="muted-link inline-block text-sm text-slate-600 hover:underline">Volver al inicio</a>
    </div>
@endsection
