@extends('layouts.app')

@section('title', 'Nueva categoría')

@section('content')
    <div class="cats-form-page">

        {{-- ===== HEADER ===== --}}
        <header class="cats-form-header">
            <div>
                <h1 class="cats-form-title">Nueva categoría</h1>
                <p class="cats-form-subtitle">Crea una categoría para clasificar tickets y analítica operativa.</p>
            </div>
            <a href="{{ route('categories.index') }}" class="btn-secondary">← Volver</a>
        </header>

        {{-- ===== ALERTS ===== --}}
        @if ($errors->any())
            <div class="alert-error">
                <p class="font-semibold mb-2">Corrige los siguientes errores:</p>
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li class="text-sm">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ===== FORM ===== --}}
        <form method="POST" action="{{ route('categories.store') }}" enctype="multipart/form-data" class="cats-form-card">
            @csrf

            <div class="cats-form-card-body">
                @include('categories.partials.form', [
                    'category'    => null,
                    'submitLabel' => 'Guardar categoría',
                ])
            </div>
        </form>

    </div>
@endsection