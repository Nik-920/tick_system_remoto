{{-- Modal de confirmación de eliminado (solo admin/super_admin) --}}
@can('delete', $ticket)
<div id="deleteTicketModal" class="fixed inset-0 z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300" style="backdrop-filter: blur(5px);">
    <button type="button" class="absolute inset-0 w-full h-full border-0 p-0 m-0 cursor-default" style="background-color: rgba(15, 23, 42, 0.55);" data-close-delete-modal aria-label="Cerrar modal" tabindex="-1"></button>

    <div class="relative w-full max-w-sm rounded-2xl p-6 transform scale-95 translate-y-4 transition-all duration-300 shadow-2xl" style="background-color: var(--bg-surface); border: 1px solid var(--border-default);">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full mb-4" style="background-color: rgba(225, 29, 72, 0.12);">
            <x-lucide-alert-triangle width="28" height="28" style="color: #e11d48;" stroke-width="2.5" />
        </div>

        <div class="text-center mb-6">
            <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">¿Eliminar este ticket?</h3>
            <p class="text-sm" style="color: var(--text-muted); line-height: 1.5;">Esta acción no se puede deshacer. Se eliminará el ticket junto con todos sus adjuntos.</p>
        </div>

        <div class="flex gap-3 justify-center mt-2">
            <button type="button" class="btn-secondary flex-1 text-center justify-center" data-close-delete-modal>
                Cancelar
            </button>
            <button type="button" class="btn-danger flex-1 text-center justify-center" data-confirm-delete-ticket>
                Sí, eliminar
            </button>
        </div>
    </div>
</div>
@endcan
