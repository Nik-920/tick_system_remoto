@extends('layouts.auth')

@section('title', 'Iniciar sesion')

@section('content')
    <div class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Iniciar sesion</h1>
            <p class="text-sm text-slate-600">Accede con tu correo institucional o cuenta del sistema.</p>
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

        @if (session('status'))
            <div class="alert-success bg-green-100 border border-green-300 text-green-800 p-3 rounded-md">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="stack space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium mb-1 text-slate-700">Correo</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="field w-full border rounded-md p-2">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium mb-1 text-slate-700">Contrasena</label>
                <input id="password" name="password" type="password" required class="field w-full border rounded-md p-2">
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="rounded border-slate-300">
                Recordarme
            </label>

            <div class="actions flex items-center justify-between pt-2">
                <div class="flex flex-col gap-1">
                    <a href="{{ route('register') }}" class="text-sm text-blue-600 hover:underline">No tienes cuenta? Registrate</a>
                    <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:underline">Olvidaste tu contrasena?</a>
                </div>
                <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Entrar</button>
            </div>
        </form>

        <a href="{{ url('/') }}" class="muted-link inline-block text-sm text-slate-600 hover:underline">Volver al inicio</a>
    </div>
@endsection
