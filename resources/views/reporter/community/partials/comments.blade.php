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
        <ul class="comm-comments__list comm-comments-v2__list"
            aria-label="Comentarios recientes"
            data-community-comments-list
            data-ticket-id="{{ $post['id'] }}">
            @foreach ($post['comments']['items'] as $comment)
                @include('reporter.community.partials.comment-list-item', [
                    'comment' => $comment,
                    'post_id' => $post['id'],
                ])
            @endforeach
        </ul>

        @if ($post['comments']['root_count'] > count($post['comments']['items']))
            <button type="button"
                    class="comm-comments__load-more comm-comments-v2__load-more"
                    data-community-comments-more
                    data-url="{{ route('reporter.community.comments.index', $post['id']) }}"
                    data-offset="{{ count($post['comments']['items']) }}">
                <x-lucide-chevron-down width="14" height="14" stroke-width="2" />
                Ver {{ $post['comments']['root_count'] - count($post['comments']['items']) }} comentario{{ ($post['comments']['root_count'] - count($post['comments']['items'])) !== 1 ? 's' : '' }} más
            </button>
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
