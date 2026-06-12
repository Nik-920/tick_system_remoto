{{-- Tabla de evidencias compartida. Espera: $items (colección de TicketMedia),
     $avatarClass (clase del avatar), $fallbackInitial, más $fmtDate/$fileNameFor del scope padre. --}}
<div class="ticket-show__table-wrap">
    <table class="ticket-show__table">
        <thead>
            <tr>
                <th scope="col">Archivo</th>
                <th scope="col">Tipo</th>
                <th scope="col">Fecha</th>
                <th scope="col">Subido por</th>
                <th scope="col">Ver</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $media)
                <tr>
                    <td class="is-strong max-w-[140px] truncate" title="{{ $fileNameFor($media) }}">
                        {{ $fileNameFor($media) }}
                    </td>
                    <td>{{ $media->file_type ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $fmtDate($media->created_at) ?? '—' }}</td>
                    <td>
                        <span class="ticket-show__avatar {{ $avatarClass }} w-6 h-6 text-[10px]"
                              title="{{ $media->uploadedBy?->name ?? '' }}">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($media->uploadedBy?->name ?? $fallbackInitial, 0, 2)) }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ $media->file_url }}" target="_blank" rel="noopener noreferrer"
                           aria-label="Ver archivo {{ $fileNameFor($media) }}"
                           class="ticket-show__action-link inline-flex items-center justify-center w-7 h-7 !p-0 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
