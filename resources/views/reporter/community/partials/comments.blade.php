{{-- ── COMMUNITY COMMENTS PARTIAL ────────────────────────────────────
     Receives $post (array from CommunityFeedQuery::toPost).
     Security contract:
     - No commenter names, emails, or PII rendered.
     - Generic author label: "Reporter de la comunidad" / "Tú".
     - Comment body escaped with {{ }} — no raw HTML.
     - Hidden/deleted comments never reach this partial (filtered in query).
     - Report reason/note/reporter never shown in reporter feed.
     - No @php blocks.
──────────────────────────────────────────────────────────────────── --}}

<section class="comm-comments" aria-label="Comentarios de la comunidad">

    {{-- ── Existing visible comments ── --}}
    @if (count($post['comments']['items']) > 0)
        <ul class="comm-comments__list" aria-label="Comentarios recientes">
            @foreach ($post['comments']['items'] as $comment)
                <li class="comm-comment">
                    <div class="comm-comment__header">
                        <span class="comm-comment__author">
                            {{ $comment['owned_by_viewer'] ? 'Tú' : 'Reporter de la comunidad' }}
                        </span>
                        @if ($comment['edited'])
                            <span class="comm-comment__edited-badge">Editado</span>
                        @endif
                        <span class="comm-comment__time">{{ $comment['created_ago'] }}</span>
                    </div>
                    <p class="comm-comment__body">{{ $comment['body'] }}</p>
                    <div class="comm-comment__actions">
                        @if ($comment['owned_by_viewer'])
                            {{-- Edit form (no JS — details/summary toggle) --}}
                            <details class="comm-comment-edit">
                                <summary class="comm-comment-edit__toggle">
                                    <x-lucide-pencil width="12" height="12" stroke-width="2" />
                                    Editar
                                </summary>
                                <div class="comm-comment-edit__form-wrap">
                                    <form method="POST"
                                          action="{{ route('reporter.community.comments.update', $comment['id']) }}"
                                          class="comm-comment-edit__form">
                                        @csrf
                                        @method('PATCH')
                                        <textarea name="body"
                                                  maxlength="500"
                                                  minlength="2"
                                                  rows="2"
                                                  required
                                                  class="comm-comment-edit__textarea"
                                                  aria-label="Editar comentario">{{ $comment['body'] }}</textarea>
                                        <div class="comm-comment-edit__actions-row">
                                            <button type="submit" class="comm-comment-edit__submit">
                                                Guardar cambios
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </details>

                            {{-- Delete form --}}
                            <form method="POST"
                                  action="{{ route('reporter.community.comments.destroy', $comment['id']) }}"
                                  class="comm-comment__delete-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="comm-comment__delete-btn"
                                        aria-label="Eliminar mi comentario">
                                    <x-lucide-trash-2 width="12" height="12" stroke-width="2" />
                                    Eliminar
                                </button>
                            </form>
                        @else
                            @if ($comment['viewer_report_pending'])
                                <span class="comm-comment__reported-badge">
                                    <x-lucide-flag width="12" height="12" stroke-width="2" />
                                    Comentario reportado
                                </span>
                            @else
                                <details class="comm-comment-report">
                                    <summary class="comm-comment-report__toggle">
                                        <x-lucide-flag width="12" height="12" stroke-width="2" />
                                        Reportar
                                    </summary>
                                    <div class="comm-comment-report__form-wrap">
                                        <form method="POST"
                                              action="{{ route('reporter.community.comment-reports.store', $comment['id']) }}"
                                              class="comm-comment-report__form">
                                            @csrf
                                            <select name="reason"
                                                    required
                                                    class="comm-comment-report__select"
                                                    aria-label="Motivo del reporte">
                                                <option value="" disabled selected>Selecciona motivo...</option>
                                                <option value="sensitive_info">Información sensible</option>
                                                <option value="inappropriate_evidence">Contenido no apto</option>
                                                <option value="incorrect_info">Contenido incorrecto</option>
                                                <option value="duplicate_or_confusing">Duplicado o confuso</option>
                                                <option value="other">Otro motivo</option>
                                            </select>
                                            <textarea name="note"
                                                      maxlength="500"
                                                      rows="2"
                                                      placeholder="Detalles adicionales (opcional)"
                                                      class="comm-comment-report__note"
                                                      aria-label="Nota adicional (opcional)"></textarea>
                                            <button type="submit" class="comm-comment-report__submit">
                                                Enviar reporte
                                            </button>
                                        </form>
                                    </div>
                                </details>
                            @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($post['comments']['count'] > 2)
            <p class="comm-comments__more-hint">
                + {{ $post['comments']['count'] - 2 }} comentario{{ ($post['comments']['count'] - 2) !== 1 ? 's' : '' }} más
            </p>
        @endif
    @else
        <p class="comm-comments__empty">Sé el primero en aportar contexto útil.</p>
    @endif

    {{-- ── Add comment form ── --}}
    <form method="POST"
          action="{{ route('reporter.community.comments.store', $post['id']) }}"
          class="comm-comments__form">
        @csrf
        <label for="comment-body-{{ $post['id'] }}" class="comm-comments__form-label">
            Comenta información útil para la comunidad. No compartas datos personales.
        </label>
        <div class="comm-comments__form-row">
            <textarea
                id="comment-body-{{ $post['id'] }}"
                name="body"
                rows="2"
                maxlength="500"
                required
                minlength="2"
                placeholder="Escribe un comentario útil..."
                class="comm-comments__textarea"
                aria-label="Escribe un comentario"></textarea>
            <button type="submit" class="comm-comments__submit-btn" aria-label="Publicar comentario">
                <x-lucide-send width="14" height="14" stroke-width="2" />
                Comentar
            </button>
        </div>
    </form>

</section>
