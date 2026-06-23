{{-- post-card/media: proxy carousel (data-comm-car), placeholder, attachment pill.
     $carImgs / $carCount are computed in the orchestrator's @php block and
     available here via Blade's inherited scope. Raw file_url is never rendered. --}}
<div class="comm-post-v2__media">
    @if ($carCount > 0)
        <div class="comm-post-v2__media-frame comm-thumb-car" data-comm-car>

            {{-- Slides --}}
            @foreach ($carImgs as $carIdx => $carUrl)
                <div class="comm-thumb-car__slide {{ $carIdx === 0 ? 'comm-thumb-car__slide--visible' : '' }}"
                     data-car-slide="{{ $carIdx }}"
                     data-community-media-frame>
                    <img
                        src="{{ $carUrl }}"
                        alt="Evidencia {{ $carIdx + 1 }} del reporte"
                        class="comm-post-v2__media-img"
                        loading="{{ $carIdx === 0 ? 'eager' : 'lazy' }}"
                        width="320"
                        height="240"
                        data-community-media-img
                    >
                    <div class="comm-post-v2__media-unavailable" data-community-media-fallback hidden aria-hidden="true">
                        <x-lucide-image-off width="26" height="26" stroke-width="1.5" />
                        <span>Vista previa no disponible</span>
                    </div>
                </div>
            @endforeach

            {{-- Bottom gradient + counter overlay --}}
            <div class="comm-post-v2__media-overlay" aria-hidden="true">
                <span class="comm-post-v2__media-counter">1 / {{ $carCount }}</span>
            </div>

            {{-- Prev / Next arrows (only when >1 image) --}}
            @if ($carCount > 1)
                <button type="button"
                        class="comm-thumb-car__arrow comm-thumb-car__arrow--prev"
                        data-car-prev
                        aria-label="Imagen anterior">
                    <x-lucide-chevron-left width="14" height="14" stroke-width="2.5" />
                </button>
                <button type="button"
                        class="comm-thumb-car__arrow comm-thumb-car__arrow--next"
                        data-car-next
                        aria-label="Imagen siguiente">
                    <x-lucide-chevron-right width="14" height="14" stroke-width="2.5" />
                </button>

                {{-- Navigation dots --}}
                <div class="comm-thumb-car__dots" aria-hidden="true">
                    @foreach ($carImgs as $carIdx => $carUrl)
                        <span class="comm-thumb-car__dot {{ $carIdx === 0 ? 'comm-thumb-car__dot--on' : '' }}"
                              data-car-dot="{{ $carIdx }}"></span>
                    @endforeach
                </div>
            @endif

        </div>
    @else
        <div class="comm-post-v2__media-frame comm-post-v2__media-placeholder" aria-hidden="true">
            <x-lucide-image width="30" height="30" stroke-width="1.5" />
            <span>Sin evidencia</span>
        </div>
    @endif

    @if ($post['has_media'])
        <span class="comm-post-v2__attachments">
            <x-lucide-paperclip width="12" height="12" stroke-width="2" aria-hidden="true" />
            {{ $post['media_count'] }} {{ $post['media_count'] === 1 ? 'archivo adjunto' : 'archivos adjuntos' }}
        </span>
    @endif
</div>
