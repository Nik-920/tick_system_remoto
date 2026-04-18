@extends('layouts.app')

@section('title', 'Nueva categoria')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Nueva categoria</h1>
                <p class="text-sm text-slate-600 mt-1">Crea una categoria para clasificar tickets y analitica.</p>
            </div>
            <a href="{{ route('categories.index') }}" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Volver</a>
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

        <form method="POST" action="{{ route('categories.store') }}" class="panel panel-pad space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium mb-1 text-slate-700">Nombre</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="field">
            </div>

            <div>
                <label for="icon" class="block text-sm font-medium mb-1 text-slate-700">Icono (opcional)</label>
                <input id="icon" name="icon" type="text" value="{{ old('icon') }}" class="field" placeholder="wrench, alert, tools">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium mb-1 text-slate-700">Descripcion</label>
                <textarea id="description" name="description" class="field" rows="4">{{ old('description') }}</textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Guardar categoria</button>
            </div>
        </form>
    </div>
@endsection
