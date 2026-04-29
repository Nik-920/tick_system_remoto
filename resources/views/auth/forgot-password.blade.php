@extends('layouts.auth')

@section('title', 'Recuperar contrasena')

@section('content')
    <div class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Recuperar contrasena</h1>
            <p class="text-sm text-slate-600">Ingresa tu correo y te enviaremos un enlace para restablecer el acceso.</p>
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

        <form method="POST" action="{{ route('password.email') }}" class="stack space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium mb-1 text-slate-700">Correo</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="field w-full border rounded-md p-2">
            </div>

            <div class="actions flex items-center justify-between pt-2">
                <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">Volver a iniciar sesion</a>
                <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Enviar enlace</button>
            </div>
        </form>

        <a href="{{ url('/') }}" class="muted-link inline-block text-sm text-slate-600 hover:underline">Volver al inicio</a>
    </div>
@endsection
