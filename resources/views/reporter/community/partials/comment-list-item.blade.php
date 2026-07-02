{{-- ── COMMUNITY COMMENT LIST ITEM ───────────────────────────────────
     Wraps a single root comment (+ up to 2 visible replies) exactly as it
     appears inside comm-comments__list. Shared by the initial feed render
     (comments.blade.php) and the "Ver más comentarios" pagination endpoint
     (CommunityCommentController::index) so both surfaces render identical
     markup — including the Editar/Eliminar/Reportar/Responder forms — and
     never drift out of sync with each other.

     Receives $comment (array from CommunityFeedQuery::mapRootsToCommentItems)
     and $post_id (string|int, the parent ticket id).
     No @php blocks — see comment-item.blade.php security contract.
──────────────────────────────────────────────────────────────────── --}}
<li class="comm-comment comm-comment-v2">
    @include('reporter.community.partials.comment-item', [
        'comment'          => $comment,
        'show_reply_form'  => true,
        'post_id'          => $post_id,
    ])

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
