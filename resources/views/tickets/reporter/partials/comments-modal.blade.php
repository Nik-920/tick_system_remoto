{{--
    Reporter Tickets — Comments Modal
    Included once in index.blade.php. JS (reporter-ticket-comments-modal.js)
    populates the body, sets the title, shows/hides the form and the
    "no comments available" footer.

    Accessibility:
      - role="dialog" + aria-modal="true" + aria-labelledby
      - tabindex="-1" on <section> so JS can focus it on open
      - Close targets carry [data-comments-modal-close]
      - Escape and overlay click → close (handled by JS)
--}}
<div id="reporter-ticket-comments-modal" class="rep-comments-modal" hidden>

    {{-- Backdrop ──────────────────────────────────────────────── --}}
    <div class="rep-comments-modal__overlay" data-comments-modal-close aria-hidden="true"></div>

    {{-- Dialog ────────────────────────────────────────────────── --}}
    <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="rep-comments-modal-title"
        class="rep-comments-modal__dialog"
        tabindex="-1"
    >
        {{-- Header ── --}}
        <header class="rep-comments-modal__header">
            <h2 class="rep-comments-modal__title" id="rep-comments-modal-title">
                Comentarios del ticket
            </h2>
            <button
                type="button"
                class="rep-comments-modal__close"
                data-comments-modal-close
                aria-label="Cerrar comentarios"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </header>

        {{-- Body — filled by JS (spinner → list or empty state) ── --}}
        <div class="rep-comments-modal__body" data-comments-modal-body></div>

        {{-- Comment form (shown only when can_comment=true) ── --}}
        <form class="rep-comments-modal__form" data-comments-modal-form hidden>
            @csrf
            <div class="rep-comments-modal__form-inner">
                <label for="rep-comments-modal-input" class="sr-only">Escribe un comentario</label>
                <textarea
                    id="rep-comments-modal-input"
                    name="body"
                    class="rep-comments-modal__textarea"
                    placeholder="Escribe un comentario…"
                    rows="2"
                    maxlength="500"
                    data-comments-modal-input
                ></textarea>
                <p class="rep-comments-modal__form-error" data-comments-modal-error aria-live="polite"></p>
            </div>
            <button type="submit" class="rep-comments-modal__submit" data-comments-modal-submit>
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
                <span class="sr-only">Enviar comentario</span>
            </button>
        </form>

        {{-- No-comment footer (shown when can_comment=false) ── --}}
        <div class="rep-comments-modal__no-comment" data-comments-modal-no-comment hidden>
            <a href="#" class="rep-btn rep-btn--ghost" data-comments-modal-detail-link>
                Ver detalle del ticket
            </a>
        </div>

    </section>
</div>
