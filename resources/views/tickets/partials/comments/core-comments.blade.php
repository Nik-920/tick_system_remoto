{{--
    Private core comments section.
    Variables: $ticket (Ticket), $coreComments (Collection<TicketComment>), $currentUserId (string)
--}}
@php
    $canComment = auth()->user()?->can('comment', $ticket) ?? false;
@endphp
<section id="core-comments" class="mt-6 ticket-show__card ticket-show__card--sectioned" aria-labelledby="core-comments-heading">

    <div class="ticket-show__section-head">
        <span class="ticket-show__section-badge" aria-hidden="true">
            <x-lucide-message-circle width="14" height="14" stroke-width="2" />
        </span>
        <h2 id="core-comments-heading" class="ticket-show__section-head-title">
            Comentarios internos
            @if ($coreComments->isNotEmpty())
                <span class="tc-count">{{ $coreComments->count() }}</span>
            @endif
        </h2>
    </div>

    <div class="ticket-show__card-body tc-comments-body">

        @if ($coreComments->isEmpty())
            <p class="tc-empty-state text-center text-gray-500 text-sm py-4">Aún no hay comentarios internos.</p>
        @else
            <div class="tc-comments-list">
                @foreach ($coreComments as $comment)
                    @include('tickets.partials.comments.core-comment-item', [
                        'comment'       => $comment,
                        'currentUserId' => $currentUserId,
                    ])
                @endforeach
            </div>
        @endif

        @if ($canComment)
            @include('tickets.partials.comments.core-comment-form', ['ticket' => $ticket])
        @endif

    </div>

</section>
