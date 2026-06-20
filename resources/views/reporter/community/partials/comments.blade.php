{{-- ── COMMUNITY COMMENTS PARTIAL ────────────────────────────────────
     Receives $post (array from CommunityFeedQuery::toPost).
     Security contract:
     - No commenter names, emails, or PII rendered.
     - Generic author label: "Reporter de la comunidad" / "Tú".
     - Comment body escaped with {{ }} — no raw HTML.
     - Hidden/deleted comments never reach this partial (filtered in query).
     - Replies are one level deep only; hidden/deleted parents hide their replies.
     - Report reason/note/reporter never shown in reporter feed.
     - No @php blocks.
──────────────────────────────────────────────────────────────────── --}}

<section class="comm-comments" aria-label="Comentarios de la comunidad">

    {{-- ── Existing visible root comments (each with up to 2 replies) ── --}}
    @if (count($post['comments']['items']) > 0)
        <ul class="comm-comments__list" aria-label="Comentarios recientes">
            @foreach ($post['comments']['items'] as $comment)
                <li class="comm-comment">
                    @include('reporter.community.partials.comment-item', ['comment' => $comment])

                    {{-- ── Replies (one level) ── --}}
                    @if (count($comment['replies']) > 0)
                        <ul class="comm-comment__replies" aria-label="Respuestas">
                            @foreach ($comment['replies'] as $reply)
                                <li class="comm-comment comm-comment--reply">
                                    @include('reporter.community.partials.comment-item', ['comment' => $reply])
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($comment['reply_count'] > count($comment['replies']))
                        <p class="comm-comments__more-hint comm-comments__more-hint--replies">
                            + {{ $comment['reply_count'] - count($comment['replies']) }}
                            respuesta{{ ($comment['reply_count'] - count($comment['replies'])) !== 1 ? 's' : '' }} más
                        </p>
                    @endif

                    {{-- ── Reply form (root comments only — caps nesting at 1) ── --}}
                    <details class="comm-reply-form">
                        <summary class="comm-reply-form__toggle">
                            <x-lucide-corner-down-right width="12" height="12" stroke-width="2" />
                            Responder
                        </summary>
                        <div class="comm-reply-form__wrap">
                            <form method="POST"
                                  action="{{ route('reporter.community.comments.store', $post['id']) }}"
                                  class="comm-reply-form__form">
                                @csrf
                                <input type="hidden" name="parent_id" value="{{ $comment['id'] }}">
                                <textarea name="body"
                                          rows="2"
                                          maxlength="500"
                                          minlength="2"
                                          required
                                          placeholder="Responde con contexto útil. No compartas datos personales."
                                          class="comm-reply-form__textarea"
                                          aria-label="Escribe una respuesta"></textarea>
                                <button type="submit" class="comm-reply-form__submit">
                                    <x-lucide-send width="12" height="12" stroke-width="2" />
                                    Responder
                                </button>
                            </form>
                        </div>
                    </details>
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
