{{-- ── COMMUNITY COMMENTS PARTIAL ────────────────────────────────────
     Receives $post (array from CommunityFeedQuery::toPost).
     Security contract:
     - No commenter email or internal IDs rendered.
     - Author label shows the commenter's display name, or "Tú" for the
       viewer's own comments (author_label, pre-resolved server-side).
     - Comment body escaped with {{ }} — no raw HTML.
     - Hidden/deleted comments never reach this partial (filtered in query).
     - Replies are one level deep only; hidden/deleted parents hide their replies.
     - Report reason/note/reporter never shown in reporter feed.
     - No @php blocks.
──────────────────────────────────────────────────────────────────── --}}

<section class="comm-comments comm-comments-v2" aria-label="Comentarios de la comunidad">

    {{-- ── Root comments ── --}}
    @if (count($post['comments']['items']) > 0)
        <ul class="comm-comments__list comm-comments-v2__list" aria-label="Comentarios recientes">
            @foreach ($post['comments']['items'] as $comment)
                <li class="comm-comment comm-comment-v2">
                    {{-- avatar + main (with Reportar + Responder in actions) --}}
                    @include('reporter.community.partials.comment-item', [
                        'comment'          => $comment,
                        'show_reply_form'  => true,
                        'post_id'          => $post['id'],
                    ])

                    {{-- ── Replies (one level) ── --}}
                    @if (count($comment['replies']) > 0)
                        <ul class="comm-comment__replies comm-replies-v2" aria-label="Respuestas">
                            @foreach ($comment['replies'] as $reply)
                                <li class="comm-comment comm-comment--reply comm-comment-v2 comm-comment-v2--reply">
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

    {{-- ── Add comment composer ── --}}
    <div class="comm-comments-composer">
        <p class="comm-comments-composer__hint">
            Comenta información útil para la comunidad. No compartas datos personales.
        </p>
        <form method="POST"
              action="{{ route('reporter.community.comments.store', $post['id']) }}"
              class="comm-comments__form">
            @csrf
            <div class="comm-comments__form-row comm-comments-composer__row">
                <textarea
                    id="comment-body-{{ $post['id'] }}"
                    name="body"
                    rows="2"
                    maxlength="500"
                    required
                    minlength="2"
                    placeholder="Escribe un comentario útil..."
                    class="comm-comments__textarea comm-comments-composer__textarea"
                    aria-label="Escribe un comentario"></textarea>
                <button type="submit"
                        class="comm-comments__submit-btn comm-comments-composer__submit"
                        aria-label="Publicar comentario">
                    <x-lucide-send width="14" height="14" stroke-width="2" />
                    Comentar
                </button>
            </div>
        </form>
    </div>

</section>
