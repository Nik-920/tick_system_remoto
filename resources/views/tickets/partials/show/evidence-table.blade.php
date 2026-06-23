{{-- Evidence card list. Expects: $items (Collection<TicketMedia>), $avatarTone, $fallbackInitial;
     $vm (TicketShowViewModel) and $ticket (Ticket) available via inherited Blade scope. --}}
<ul class="ticket-show__evidence-list" role="list">
    @foreach ($items as $media)
        @php
            $evType    = (string) ($media->file_type ?? '');
            $evIsImage = str_starts_with($evType, 'image');
            $evIsPdf   = str_contains($evType, 'pdf');
            $evLabel   = $evType !== ''
                ? (str_contains($evType, '/') ? explode('/', $evType)[1] : $evType)
                : '—';
            $evIconMod = $evIsImage ? 'ticket-show__evidence-icon--image'
                : ($evIsPdf ? 'ticket-show__evidence-icon--pdf' : 'ticket-show__evidence-icon--file');
        @endphp
        <li class="ticket-show__evidence-card">

            <div class="ticket-show__evidence-icon {{ $evIconMod }}" aria-hidden="true">
                @if ($evIsImage)
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path stroke-linecap="round" stroke-linejoin="round" d="m21 15-5-5L5 21"/></svg>
                @elseif ($evIsPdf)
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                @else
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
                @endif
            </div>

            <div class="ticket-show__evidence-info">
                <span class="ticket-show__evidence-name" title="{{ $vm->fileNameFor($media) }}">{{ $vm->fileNameFor($media) }}</span>
                <div class="ticket-show__evidence-meta">
                    <span class="ticket-show__evidence-type-badge">{{ $evLabel }}</span>
                    <span class="ticket-show__evidence-date">{{ $vm->fmtDate($media->created_at) ?? '—' }}</span>
                </div>
                <div class="ticket-show__evidence-uploader">
                    <x-avatar
                        :initials="$vm->initials($media->uploadedBy?->name, 2, $fallbackInitial)"
                        :tone="$avatarTone"
                        class="w-5 h-5 text-[10px]"
                        :title="$media->uploadedBy?->name ?? ''"
                        aria-hidden="true"
                    />
                    <span class="ticket-show__evidence-uploader-name">{{ $media->uploadedBy?->name ?? $fallbackInitial }}</span>
                </div>
            </div>

            <div class="ticket-show__evidence-actions">
                <a href="{{ route('tickets.media.view', [$ticket->id, $media->id]) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="Ver archivo {{ $vm->fileNameFor($media) }}"
                   class="ticket-show__evidence-action">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                </a>
            </div>

        </li>
    @endforeach
</ul>
