/**
 * community-social-actions.js
 *
 * Progressive-enhancement layer for Community feed social actions.
 *
 * Without JS: standard form POST/DELETE → server redirect (unchanged).
 * With JS:    intercepts [data-community-social-form] submits, applies an
 *             optimistic UI update, fetches JSON, then syncs with real counts.
 *             If the request fails the UI rolls back and announces the error
 *             via a screen-reader live region.
 *
 * CSRF is sent via the hidden _token field already in every Blade form.
 * Method spoofing (_method=DELETE) is preserved via FormData when applicable.
 *
 * No external dependencies. No Alpine/Vue/React.
 */

// ── helpers ──────────────────────────────────────────────────────────────────

function getCsrfToken() {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
        document.querySelector('input[name="_token"]')?.value ??
        ''
    );
}

function announce(statusEl, message) {
    if (!statusEl) return;
    statusEl.textContent = '';
    // Double rAF forces the live-region to re-announce even identical text.
    requestAnimationFrame(() => {
        requestAnimationFrame(() => { statusEl.textContent = message; });
    });
}

// ── optimistic helpers ────────────────────────────────────────────────────────

/**
 * Apply an optimistic toggle to button, count, and aria attributes.
 * Returns a snapshot of pre-change state so we can roll back on error.
 *
 * @param {HTMLFormElement} form
 * @returns {{ btnClass: string, ariaPressed: string, ariaLabel: string,
 *             countText: string, labelText: string, dataActive: string }}
 */
function applyOptimistic(form) {
    const btn       = form.querySelector('[data-community-action-button]');
    const countEl   = form.querySelector('[data-community-action-count]');
    const labelEl   = form.querySelector('[data-community-action-label]');

    const wasActive     = form.dataset.active === 'true';
    const action        = form.dataset.communityAction;
    const modifier      = form.dataset.reactionModifier ?? 'saved';

    // Snapshot
    const snapshot = {
        btnClass:   btn?.className ?? '',
        ariaPressed: btn?.getAttribute('aria-pressed') ?? 'false',
        ariaLabel:  btn?.getAttribute('aria-label') ?? '',
        countText:  countEl?.textContent ?? '',
        labelText:  labelEl?.textContent ?? '',
        dataActive: form.dataset.active,
    };

    const nextActive = !wasActive;

    // ── button classes ──────────────────────────────────────────────────────
    if (btn) {
        if (nextActive) {
            btn.classList.add('comm-action-btn--active');
            if (action === 'reaction') btn.classList.add(`comm-action-btn--${modifier}`);
            if (action === 'save')     btn.classList.add('comm-action-btn--saved');
        } else {
            btn.classList.remove('comm-action-btn--active');
            if (action === 'reaction') btn.classList.remove(`comm-action-btn--${modifier}`);
            if (action === 'save')     btn.classList.remove('comm-action-btn--saved');
        }

        btn.setAttribute('aria-pressed', String(nextActive));

        if (action === 'save') {
            const newLabel = nextActive ? 'Quitar de guardados' : 'Guardar reporte';
            btn.setAttribute('aria-label', newLabel);
        } else {
            const reactionLabel = form.dataset.reactionType ?? '';
            const humanLabel    = labelEl?.textContent.trim() ?? reactionLabel;
            btn.setAttribute('aria-label', nextActive
                ? `Quitar reacción ${humanLabel}`
                : `Marcar como ${humanLabel}`);
        }
    }

    // ── count ───────────────────────────────────────────────────────────────
    if (countEl && action !== 'save') {
        const current = parseInt(countEl.textContent, 10) || 0;
        const next    = Math.max(0, current + (nextActive ? 1 : -1));
        countEl.textContent = next > 0 ? String(next) : '';
    }

    // ── save label ──────────────────────────────────────────────────────────
    if (labelEl && action === 'save') {
        labelEl.textContent = nextActive ? 'Guardado' : 'Guardar';
    }

    // ── data-active ─────────────────────────────────────────────────────────
    form.dataset.active = String(nextActive);

    return snapshot;
}

function rollback(form, snapshot) {
    const btn     = form.querySelector('[data-community-action-button]');
    const countEl = form.querySelector('[data-community-action-count]');
    const labelEl = form.querySelector('[data-community-action-label]');

    if (btn) {
        btn.className = snapshot.btnClass;
        btn.setAttribute('aria-pressed', snapshot.ariaPressed);
        btn.setAttribute('aria-label',   snapshot.ariaLabel);
    }
    if (countEl) countEl.textContent = snapshot.countText;
    if (labelEl) labelEl.textContent = snapshot.labelText;

    form.dataset.active = snapshot.dataActive;
}

/**
 * Sync the DOM with the real data returned by the JSON response.
 * This corrects any drift from the optimistic estimate.
 *
 * @param {HTMLFormElement} form
 * @param {object} data  — JSON payload from backend
 */
function syncWithResponse(form, data) {
    const btn     = form.querySelector('[data-community-action-button]');
    const countEl = form.querySelector('[data-community-action-count]');
    const labelEl = form.querySelector('[data-community-action-label]');
    const active  = data.active === true;

    // Count: for reactions use the specific type count; for saves use count.
    let realCount = 0;
    if (data.kind === 'reaction' && data.counts && data.type) {
        realCount = data.counts[data.type] ?? data.count ?? 0;
    } else if (data.kind === 'save') {
        // Saves don't show a count in the button — skip.
        realCount = -1;
    } else {
        realCount = data.count ?? 0;
    }

    if (countEl && realCount >= 0) {
        countEl.textContent = realCount > 0 ? String(realCount) : '';
    }

    // Ensure data-active matches reality (idempotency cases).
    form.dataset.active = String(active);

    // Ensure aria-pressed matches.
    if (btn) {
        btn.setAttribute('aria-pressed', String(active));
    }
}

// ── fetch ─────────────────────────────────────────────────────────────────────

/**
 * Send the social action via fetch, returning the JSON payload.
 * Laravel method-spoofing is preserved: forms always use method="POST"
 * at the network level and send `_method=DELETE` in the body when needed.
 *
 * @param {string} url
 * @param {FormData} formData
 * @returns {Promise<object>}
 */
async function sendAction(url, formData) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
    });

    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }

    const data = await res.json();

    if (!data.ok) {
        throw new Error('Server returned ok=false');
    }

    return data;
}

// ── main handler ──────────────────────────────────────────────────────────────

function handleSocialFormSubmit(e) {
    const form = /** @type {HTMLFormElement} */ (e.target);

    if (!form.matches('[data-community-social-form]')) return;

    // Guard: already in-flight.
    if (form.dataset.loading === 'true') {
        e.preventDefault();
        return;
    }

    e.preventDefault();

    const wasActive = form.dataset.active === 'true';
    const action    = form.dataset.communityAction; // 'reaction' | 'save'

    // Determine URL and body.
    const url      = wasActive ? form.dataset.destroyUrl : form.dataset.storeUrl;
    const formData = new FormData();
    formData.set('_token', getCsrfToken());

    if (wasActive) {
        // Simulates DELETE via method-spoofing, same as Blade @method('DELETE').
        formData.set('_method', 'DELETE');
    } else if (action === 'reaction') {
        formData.set('type', form.dataset.reactionType ?? '');
    }

    // Find the live-region nearest to this card for accessible feedback.
    const article   = form.closest('article[id^="ticket-"]');
    const statusEl  = article?.querySelector('[data-community-social-status]') ?? null;

    // Set loading state.
    const btn = form.querySelector('[data-community-action-button]');
    form.dataset.loading = 'true';
    btn?.classList.add('comm-action-btn--loading');

    // Optimistic update.
    const snapshot = applyOptimistic(form);

    sendAction(url, formData)
        .then((data) => {
            syncWithResponse(form, data);

            const msg = action === 'save'
                ? (data.active ? 'Guardado.' : 'Guardado eliminado.')
                : (data.active ? 'Reacción guardada.' : 'Reacción eliminada.');
            announce(statusEl, msg);
        })
        .catch(() => {
            rollback(form, snapshot);
            btn?.classList.add('comm-action-btn--error');
            setTimeout(() => btn?.classList.remove('comm-action-btn--error'), 2000);
            announce(statusEl, 'No se pudo guardar. Inténtalo de nuevo.');
        })
        .finally(() => {
            form.dataset.loading = 'false';
            btn?.classList.remove('comm-action-btn--loading');
        });
}

// ── init ──────────────────────────────────────────────────────────────────────

export function init() {
    // Use capture=true so we intercept before any other listener.
    document.addEventListener('submit', handleSocialFormSubmit, true);
}
