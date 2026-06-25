{{--
    Renders a single private core comment.
    Variables: $comment (TicketComment with user loaded), $currentUserId (string)
--}}
@php
    use App\Support\LocalTime;
    $isOwn = (string) $comment->user_id === $currentUserId;
    $authorName = $comment->user?->name ?? 'Usuario';
    $createdLabel = LocalTime::format($comment->created_at) ?? '—';
@endphp
<div class="tc-comment {{ $isOwn ? 'tc-comment--own' : '' }}">
    <x-avatar :name="$authorName" baseClass="tc-comment__avatar" />
    <div class="tc-comment__bubble">
        <div class="tc-comment__meta">
            <span class="tc-comment__author">{{ $isOwn ? 'Tú' : $authorName }}</span>
            <time class="tc-comment__time" datetime="{{ $comment->created_at?->toIso8601String() }}">
                {{ $createdLabel }}
            </time>
        </div>
        <p class="tc-comment__body">{{ $comment->body }}</p>
    </div>
</div>
