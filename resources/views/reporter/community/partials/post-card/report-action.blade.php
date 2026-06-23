{{-- post-card/report-action: Reportar publicación — pending state or form --}}
<div class="comm-post-v2__actions-end">
    @if ($post['viewer_report_pending'])
        <span class="comm-action-btn comm-action-btn--reported" aria-label="Reporte enviado">
            <x-lucide-flag width="15" height="15" stroke-width="2" />
            <span class="comm-action-btn__label">Reporte enviado</span>
        </span>
    @else
        <details class="comm-report-details">
            <summary class="comm-report-summary comm-report-summary--danger" aria-label="Reportar publicación">
                <x-lucide-flag width="15" height="15" stroke-width="2" />
                <span class="comm-action-btn__label">Reportar</span>
            </summary>
            <div class="comm-report-form-wrap">
                <form method="POST"
                      action="{{ route('reporter.community.reports.store', $post['id']) }}"
                      class="comm-report-form">
                    @csrf
                    <select name="reason" required class="comm-report-select" aria-label="Motivo del reporte">
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
                              class="comm-report-textarea"
                              aria-label="Detalle adicional"></textarea>
                    <button type="submit" class="comm-report-btn">Enviar reporte</button>
                </form>
            </div>
        </details>
    @endif
</div>
