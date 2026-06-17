{{-- ⑥ Evidencias / adjuntos --}}
<section id="evidence" class="mt-6 ticket-show__card" aria-labelledby="evidence-heading">
    <h2 id="evidence-heading" class="ticket-show__title">
        <span class="ticket-show__section-number" aria-hidden="true">6</span>
        Evidencias / adjuntos
    </h2>

    <div class="grid gap-6 md:grid-cols-2">

        {{-- Evidencias del reporter --}}
        @include('tickets.partials.show.evidence-section', [
            'title'           => 'Evidencias del reporter',
            'tone'            => 'rose',
            'count'           => $vm->reporterEvidenceCount(),
            'hasEvidence'     => $vm->hasReporterEvidence(),
            'evidence'        => $vm->reporterEvidence(),
            'avatarTone'      => 'rose',
            'fallbackInitial' => 'R',
            'vm'              => $vm,
        ])

        {{-- Evidencias de maintenance --}}
        <div>
            @include('tickets.partials.show.evidence-section', [
                'title'           => 'Evidencias de maintenance',
                'tone'            => 'primary',
                'count'           => $vm->maintenanceEvidenceCount(),
                'hasEvidence'     => $vm->hasMaintenanceEvidence(),
                'evidence'        => $vm->maintenanceEvidence(),
                'avatarTone'      => 'primary',
                'fallbackInitial' => 'M',
                'vm'              => $vm,
            ])

            {{-- Adjuntar evidencia (solo maintenance asignado / admin) --}}
            @if ($vm->canEditOperational)
                <div class="mt-4" data-evidence-uploader>
                    <label for="evidence-input" class="ticket-show__dropzone" data-evidence-dropzone>
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l-5-5-5 5M12 3v12"/></svg>
                        <span class="text-sm font-semibold">Adjuntar evidencia</span>
                        <span class="text-xs">Arrastra archivos aquí o haz clic para seleccionarlos. JPG, PNG, WebP, PDF, Word, Excel o MP4 — máx. 10 MB c/u, hasta 5 archivos.</span>
                    </label>
                    <input type="file" id="evidence-input" name="evidence[]" multiple form="maintenance-edit-form"
                           accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.mp4"
                           class="sr-only">

                    <div class="ticket-show__file-list hidden" data-evidence-list aria-live="polite"></div>

                    @error('evidence')
                        <p class="ticket-show__error">{{ $message }}</p>
                    @enderror
                    @error('evidence.*')
                        <p class="ticket-show__error">{{ $message }}</p>
                    @enderror

                    <p class="ticket-show__hint mt-2">
                        Los archivos seleccionados se subirán al presionar <strong>Guardar cambios</strong> en la tarjeta de información principal.
                    </p>
                </div>
            @endif
        </div>

    </div>
</section>
