@if ($vm->shouldShowDuplicateWarning())
    @include('tickets.partials.show.duplicate-banner.warning', [
        'ticket' => $ticket,
        'vm' => $vm,
        'duplicateExplanation' => $duplicateExplanation,
    ])
@elseif ($vm->duplicateEmbedding() && $vm->duplicateEmbedding()->isDismissedDuplicate())
    @include('tickets.partials.show.duplicate-banner.dismissed', [
        'ticket' => $ticket,
        'vm' => $vm,
    ])
@endif
