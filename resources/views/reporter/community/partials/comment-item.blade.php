{{-- ── SINGLE COMMUNITY COMMENT / REPLY ──────────────────────────────
     Receives $comment (array from CommunityFeedQuery::mapCommentItem).
     Used for both root comments and one-level replies.

     Optional variables (root comments only):
       $show_reply_form  bool — renders the Responder form in the actions row.
       $post_id          string|int — target post for reply store route.

     Security contract:
     - No commenter names, emails, or PII rendered.
     - Generic author label: "Reporter de la comunidad" / "Tú".
     - Body escaped with {{ }} — no raw HTML.
     - Report reason/note/reporter never shown here.
     - Role label is not PII (reporter/maintenance/admin categories only).
     - No @php blocks.
──────────────────────────────────────────────────────────────────── --}}

{{-- avatar + main in a horizontal row; replies and reply form sit below as <li> siblings --}}
<div class="comm-comment-v2__row">

    <div class="comm-comment-v2__avatar comm-comment-v2__avatar--{{ $comment['role_tone'] }}"
         aria-hidden="true">
        {{ $comment['author_initials'] }}
    </div>

    <div class="comm-comment-v2__main">
        <div class="comm-comment__header">
            <span class="comm-comment__author">
                {{ $comment['owned_by_viewer'] ? 'Tú' : 'Reporter de la comunidad' }}
            </span>
            <span class="comm-comment-v2__role comm-comment-v2__role--{{ $comment['role_tone'] }}">
                {{ $comment['role_label'] }}
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
                    <details class="comm-comment-report comm-comment-report-v2">
                        <summary class="comm-comment-report__toggle comm-comment-report-v2__toggle">
                            <x-lucide-flag width="12" height="12" stroke-width="2" />
                            Reportar
                        </summary>
                        <div class="comm-comment-report__form-wrap comm-comment-report-v2__panel">
                            <p class="comm-comment-report-v2__title">Reportar comentario</p>
                            <form method="POST"
                                  action="{{ route('reporter.community.comment-reports.store', $comment['id']) }}"
                                  class="comm-comment-report__form comm-comment-report-v2__form">
                                @csrf
                                <select name="reason"
                                        required
                                        class="comm-comment-report__select comm-comment-report-v2__select"
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
                                          class="comm-comment-report__note comm-comment-report-v2__note"
                                          aria-label="Nota adicional (opcional)"></textarea>
                                <div class="comm-comment-report-v2__actions-row">
                                    <button type="submit"
                                            class="comm-comment-report__submit comm-comment-report-v2__submit">
                                        <x-lucide-flag width="12" height="12" stroke-width="2" />
                                        Enviar reporte
                                    </button>
                                    <button type="button"
                                            class="comm-comment-report-v2__cancel"
                                            onclick="this.closest('details').removeAttribute('open')">
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </details>
                @endif
            @endif

            {{-- Responder toggle (root comments only — passed via $show_reply_form) --}}
            @if (!empty($show_reply_form ?? false))
                <details class="comm-reply-form comm-reply-form-v2">
                    <summary class="comm-reply-form__toggle comm-reply-form-v2__toggle">
                        <x-lucide-corner-down-right width="12" height="12" stroke-width="2" />
                        Responder
                    </summary>
                    <div class="comm-reply-form__wrap comm-reply-form-v2__wrap">
                        <form method="POST"
                              action="{{ route('reporter.community.comments.store', $post_id) }}"
                              class="comm-reply-form__form comm-reply-form-v2__form">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $comment['id'] }}">
                            <textarea name="body"
                                      rows="2"
                                      maxlength="500"
                                      minlength="2"
                                      required
                                      placeholder="Escribe tu respuesta..."
                                      class="comm-reply-form__textarea comm-reply-form-v2__textarea"
                                      aria-label="Escribe una respuesta"></textarea>
                            <div class="comm-reply-form-v2__actions">
                                <button type="submit"
                                        class="comm-reply-form__submit comm-reply-form-v2__submit">
                                    <x-lucide-send width="12" height="12" stroke-width="2" />
                                    Responder
                                </button>
                                <button type="button"
                                        class="comm-reply-form-v2__cancel"
                                        onclick="this.closest('details').removeAttribute('open')">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </details>
            @endif
        </div>
    </div>

</div>
