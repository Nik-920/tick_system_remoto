{{-- post-card/report-action: Reportar publicación — pending state or form --}}
<div class="comm-post-v2__actions-end">
    @if ($post['viewer_report_pending'])
        <span class="comm-action-btn comm-action-btn--reported" aria-label="Reporte enviado">
            <x-lucide-flag width="15" height="15" stroke-width="2" />
            <span class="comm-action-btn__label">Reporte enviado</span>
        </span>
    @else
        <details class="comm-report-details comm-report-v2">
            <summary class="comm-report-summary comm-report-summary--danger" aria-label="Reportar publicación">
                <x-lucide-flag width="15" height="15" stroke-width="2" />
                <span class="comm-action-btn__label">Reportar</span>
            </summary>
            <div class="comm-report-form-wrap comm-report-v2__panel">
                <p class="comm-report-v2__title">Reportar publicación</p>
                <form method="POST"
                      action="{{ route('reporter.community.reports.store', $post['id']) }}"
                      class="comm-report-form comm-report-v2__form">
                    @csrf
                    <select name="reason"
                            required
                            class="comm-report-select comm-report-v2__select"
                            aria-label="Motivo del reporte">
                        <option value="" disabled selected>Selecciona un motivo...</option>
                        <option value="sensitive_info">Información sensible</option>
                        <option value="inappropriate_evidence">Evidencia no apta</option>
                        <option value="incorrect_info">Contenido incorrecto</option>
                        <option value="duplicate_or_confusing">Duplicado o confuso</option>
                        <option value="other">Otro motivo</option>
                    </select>
                    <textarea name="note"
                              maxlength="500"
                              rows="2"
                              placeholder="Detalle adicional (opcional)"
                              class="comm-report-textarea comm-report-v2__note"
                              aria-label="Detalle adicional"></textarea>
                    <div class="comm-report-v2__actions-row">
                        <button type="submit" class="comm-report-btn comm-report-v2__submit">
                            <x-lucide-flag width="12" height="12" stroke-width="2" />
                            Enviar reporte
                        </button>
                        <button type="button"
                                class="comm-report-v2__cancel"
                                onclick="this.closest('details').removeAttribute('open')">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </details>
    @endif
</div>
