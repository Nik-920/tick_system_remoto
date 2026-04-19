@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <section class="panel panel-pad border border-slate-200 bg-slate-50/70">
        <h1 class="text-2xl font-black text-slate-900">Dashboard</h1>
        <p class="text-sm text-slate-600 mt-2">
            Esta vista generica se mantiene solo como respaldo tecnico. La experiencia principal ahora se entrega por perfil de rol.
        </p>
        <a href="{{ route('dashboard.index') }}" class="btn-primary inline-block mt-4 bg-slate-900 text-white hover:bg-slate-800">Ir al dashboard activo</a>
    </section>
@endsection
