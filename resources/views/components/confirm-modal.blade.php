{{-- Global Confirmation Modal --}}
<div id="globalConfirmModal" class="fixed inset-0 z-[100] flex items-center justify-center hidden opacity-0 transition-opacity duration-300" style="backdrop-filter: blur(5px);">
    <button type="button" id="globalConfirmModalBackdrop" class="absolute inset-0 w-full h-full border-0 p-0 m-0 cursor-default" style="background-color: rgba(15, 23, 42, 0.55);" aria-label="Cerrar modal" tabindex="-1"></button>

    <div class="relative w-full max-w-sm rounded-2xl p-6 transform scale-95 translate-y-4 transition-all duration-300 shadow-2xl" style="background-color: var(--bg-surface); border: 1px solid var(--border-default);">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full mb-4" style="background-color: rgba(59, 130, 246, 0.12);">
            <x-lucide-alert-circle width="28" height="28" style="color: var(--color-primary);" stroke-width="2.5" />
        </div>

        <div class="text-center mb-6">
            <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Confirmación</h3>
            <p id="globalConfirmModalText" class="text-sm" style="color: var(--text-muted); line-height: 1.5;"></p>
        </div>

        <div class="flex gap-3 justify-center mt-2">
            <button type="button" id="globalConfirmModalCancel" class="c-btn c-btn--ghost flex-1 text-center justify-center">
                Cancelar
            </button>
            <button type="button" id="globalConfirmModalConfirm" class="c-btn c-btn--primary flex-1 text-center justify-center">
                Aceptar
            </button>
        </div>
    </div>
</div>
