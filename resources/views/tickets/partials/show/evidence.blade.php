{{-- ⑤ Evidencias / adjuntos --}}
<section id="evidence" class="mt-6 ticket-show__card ticket-show__card--sectioned" aria-labelledby="evidence-heading">

    <div class="ticket-show__section-head">
        <span class="ticket-show__section-badge" aria-hidden="true">5</span>
        <h2 id="evidence-heading" class="ticket-show__section-head-title">Evidencias / adjuntos</h2>
    </div>

    <div class="ticket-show__card-body">
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
                            <span class="text-xs">Arrastra archivos aquí o haz clic para seleccionarlos. JPG, PNG, WebP, PDF, TXT o Word — máx. 5 MB c/u, hasta 5 archivos.</span>
                        </label>
                        <input type="file" id="evidence-input" name="evidence[]" multiple form="maintenance-edit-form"
                               accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.doc,.docx"
                               class="sr-only"
                               data-upload-guard
                               data-max-file-size="5242880"
                               data-max-file-size-label="5 MB"
                               data-max-files="5"
                               data-max-total-size="26214400"
                               data-max-total-size-label="25 MB"
                               data-allowed-extensions="jpg,jpeg,png,webp,pdf,txt,doc,docx">

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
    </div>
</section>
