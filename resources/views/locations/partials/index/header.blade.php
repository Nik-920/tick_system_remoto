{{-- Encabezado del módulo Ubicaciones --}}
<div class="loc-idx-header">
    <div>
        <h1 class="loc-idx-title">Ubicaciones</h1>
        <p class="loc-idx-subtitle">Gestiona los espacios físicos, laboratorios y aulas del sistema.</p>
    </div>

    <a href="{{ route('locations.create') }}" class="loc-idx-btn-new">
        <span class="loc-idx-btn-new__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3">
                <path d="M12 5v14M5 12h14" />
            </svg>
        </span>
        Nueva ubicación
    </a>
</div>
