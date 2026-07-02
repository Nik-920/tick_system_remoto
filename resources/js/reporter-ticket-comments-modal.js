/**
 * reporter-ticket-comments-modal.js
 *
 * Opens the reporter comments modal when a
 * [data-reporter-ticket-comments-trigger] button is clicked, loads visible
 * comments for that ticket via the JSON endpoint, renders them safely using
 * DOM APIs (never innerHTML for user content), and optionally submits a new
 * comment via fetch.
 *
 * Accessibility: focus trap, Escape closes, aria-expanded on trigger, focus
 * returns to trigger on close.
 *
 * No PII: the server sends the commenter's display name (author_label),
 * pre-resolved avatar initials, a role badge (role_label/role_tone), body
 * text, and a relative timestamp — never email, user_id, or other IDs.
 */

// ── CSRF ─────────────────────────────────────────────────────────────────────

function getCsrfToken() {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
        document.querySelector('input[name="_token"]')?.value ??
        ''
    );
}

// ── Module-level refs (resolved once on init) ─────────────────────────────────

let modal       = null;
let titleEl     = null;
let bodyEl      = null;
let form        = null;
let noCommentEl = null;
let detailLink  = null;
let textarea    = null;
let submitBtn   = null;
let errorEl     = null;

let activeTrigger  = null;
let activeStoreUrl = null;

// ── Modal open / close ────────────────────────────────────────────────────────

function open() {
    modal.hidden = false;
    // rAF: ensure the element is painted in its "initial" (off-screen/invisible)
    // state before the transition class is added, so CSS transitions fire.
    requestAnimationFrame(() => {
        modal.classList.add('rep-comments-modal--open');
    });
    document.body.classList.add('rep-modal-open');
    // Delay focus until after the CSS transition begins so screen readers
    // announce the dialog title, not the triggering element.
    setTimeout(() => {
        const closeBtn = modal.querySelector('[data-comments-modal-close]:not(.rep-comments-modal__overlay)');
        if (closeBtn) { closeBtn.focus(); }
    }, 40);
}

function close() {
    if (!modal || modal.hidden) { return; }
    modal.classList.add('rep-comments-modal--leaving');
    modal.classList.remove('rep-comments-modal--open');
    setTimeout(() => {
        modal.hidden = true;
        modal.classList.remove('rep-comments-modal--leaving');
        document.body.classList.remove('rep-modal-open');
        if (activeTrigger) {
            activeTrigger.setAttribute('aria-expanded', 'false');
            activeTrigger.focus();
            activeTrigger = null;
        }
        activeStoreUrl = null;
    }, 250);
}

// ── Body helpers (spinner / error) ────────────────────────────────────────────

function clearBody() {
    while (bodyEl.firstChild) { bodyEl.removeChild(bodyEl.firstChild); }
}

function showSpinner() {
    clearBody();
    const wrap = document.createElement('div');
    wrap.className = 'rep-comments-modal__spinner';
    wrap.setAttribute('role', 'status');
    wrap.setAttribute('aria-label', 'Cargando comentarios…');
    for (let i = 0; i < 3; i++) { wrap.appendChild(document.createElement('span')); }
    bodyEl.appendChild(wrap);
}

function showError(msg) {
    clearBody();
    const el = document.createElement('div');
    el.className = 'rep-comments-modal__error';
    el.setAttribute('role', 'alert');
    el.textContent = msg || 'No se pudieron cargar los comentarios.';
    bodyEl.appendChild(el);
}

// ── Comment rendering ─────────────────────────────────────────────────────────

function buildAvatar(comment) {
    const av = document.createElement('div');
    av.className = 'rep-comments-modal__avatar rep-comments-modal__avatar--' + comment.role_tone;
    av.setAttribute('aria-hidden', 'true');
    av.textContent = comment.author_initials; // safe: server-controlled string
    return av;
}

function buildCommentNode(comment, isReply) {
    const wrap = document.createElement('div');
    wrap.className = isReply
        ? 'rep-comments-modal__item rep-comments-modal__item--reply'
        : 'rep-comments-modal__item';

    wrap.appendChild(buildAvatar(comment));

    const bubble = document.createElement('div');
    bubble.className = 'rep-comments-modal__bubble';

    // ── meta row ──
    const meta = document.createElement('div');
    meta.className = 'rep-comments-modal__meta';

    const author = document.createElement('span');
    author.className = 'rep-comments-modal__author';
    author.textContent = comment.author_label; // safe: server-controlled string
    meta.appendChild(author);

    const role = document.createElement('span');
    role.className = 'rep-comments-modal__role rep-comments-modal__role--' + comment.role_tone;
    role.textContent = comment.role_label; // safe: server-controlled string
    meta.appendChild(role);

    if (comment.owned_by_viewer) {
        const badge = document.createElement('span');
        badge.className = 'rep-comments-modal__you';
        badge.textContent = 'tú';
        meta.appendChild(badge);
    }

    if (comment.edited) {
        const editBadge = document.createElement('span');
        editBadge.className = 'rep-comments-modal__edited';
        editBadge.textContent = 'Editado';
        meta.appendChild(editBadge);
    }

    const time = document.createElement('time');
    time.className = 'rep-comments-modal__time';
    time.textContent = comment.created_at_label; // server-controlled (diffForHumans)
    meta.appendChild(time);

    bubble.appendChild(meta);

    // ── body text — textContent prevents XSS ──
    const text = document.createElement('p');
    text.className = 'rep-comments-modal__text';
    text.textContent = comment.body;
    bubble.appendChild(text);

    wrap.appendChild(bubble);
    return wrap;
}

function renderAll(data) {
    clearBody();

    if (data.count === 0) {
        const empty = document.createElement('div');
        empty.className = 'rep-comments-modal__empty';
        empty.setAttribute('role', 'status');
        const p = document.createElement('p');
        p.textContent = 'Aún no hay comentarios visibles para este ticket.';
        empty.appendChild(p);
        bodyEl.appendChild(empty);
        return;
    }

    const list = document.createElement('div');
    list.className = 'rep-comments-modal__list';

    data.comments.forEach((c) => {
        list.appendChild(buildCommentNode(c, false));
        if (Array.isArray(c.replies) && c.replies.length > 0) {
            const repliesWrap = document.createElement('div');
            repliesWrap.className = 'rep-comments-modal__replies';
            c.replies.forEach((r) => { repliesWrap.appendChild(buildCommentNode(r, true)); });
            list.appendChild(repliesWrap);
        }
    });

    bodyEl.appendChild(list);
}

function appendComment(comment) {
    let list = bodyEl.querySelector('.rep-comments-modal__list');
    if (!list) {
        // Replace empty-state with a real list on first comment.
        clearBody();
        list = document.createElement('div');
        list.className = 'rep-comments-modal__list';
        bodyEl.appendChild(list);
    }
    list.appendChild(buildCommentNode(comment, false));
    bodyEl.scrollTop = bodyEl.scrollHeight;
}

function bumpTriggerCount(trigger, delta) {
    const countSpan = trigger.querySelector('span:not(.sr-only)');
    if (!countSpan) { return; }
    const next = Math.max(0, (parseInt(countSpan.textContent, 10) || 0) + delta);
    countSpan.textContent = next > 0 ? String(next) : '';
}

// ── Fetch comments ────────────────────────────────────────────────────────────

function loadComments(url, storeUrl, ticketRef, detailUrl) {
    if (titleEl) { titleEl.textContent = 'Comentarios del ticket ' + ticketRef; }
    if (form)        { form.hidden = true; }
    if (noCommentEl) { noCommentEl.hidden = true; }
    if (errorEl)     { errorEl.textContent = ''; }
    activeStoreUrl = storeUrl || null;

    showSpinner();

    fetch(url, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    })
        .then((res) => {
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
            return res.json();
        })
        .then((data) => {
            renderAll(data);
            if (data.can_comment && activeStoreUrl) {
                if (form) { form.hidden = false; }
            } else {
                if (noCommentEl) { noCommentEl.hidden = false; }
                if (detailLink && detailUrl) { detailLink.href = detailUrl; }
            }
        })
        .catch(() => {
            showError('No se pudieron cargar los comentarios. Inténtalo de nuevo.');
        });
}

// ── Form submit ───────────────────────────────────────────────────────────────

function handleFormSubmit(e) {
    e.preventDefault();
    if (!textarea || !activeStoreUrl) { return; }

    const body = textarea.value.trim();
    if (errorEl) { errorEl.textContent = ''; }

    if (body.length < 2) {
        if (errorEl) { errorEl.textContent = 'El comentario debe tener al menos 2 caracteres.'; }
        textarea.focus();
        return;
    }

    if (submitBtn) { submitBtn.disabled = true; }

    fetch(activeStoreUrl, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ body }),
    })
        .then((res) => res.json().then((data) => ({ status: res.status, data })))
        .then(({ status, data }) => {
            if (status === 201 && data.ok) {
                appendComment(data.comment);
                textarea.value = '';
                if (activeTrigger) { bumpTriggerCount(activeTrigger, 1); }
            } else if (data.errors?.body) {
                if (errorEl) { errorEl.textContent = data.errors.body[0]; }
            } else if (data.message) {
                if (errorEl) { errorEl.textContent = data.message; }
            }
        })
        .catch(() => {
            if (errorEl) { errorEl.textContent = 'No se pudo enviar. Inténtalo de nuevo.'; }
        })
        .finally(() => {
            if (submitBtn) { submitBtn.disabled = false; }
            textarea.focus();
        });
}

// ── Focus trap ────────────────────────────────────────────────────────────────

function trapFocus(e) {
    if (e.key !== 'Tab' || !modal || modal.hidden) { return; }
    const dialog = modal.querySelector('.rep-comments-modal__dialog');
    if (!dialog) { return; }
    const focusable = Array.from(
        dialog.querySelectorAll(
            'button:not([disabled]), [href], input:not([hidden]), textarea, [tabindex]:not([tabindex="-1"])'
        )
    ).filter((el) => !el.disabled && !el.closest('[hidden]') && el.offsetParent !== null);
    if (focusable.length < 2) { return; }
    const first = focusable[0];
    const last  = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault(); last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault(); first.focus();
    }
}

// ── Init ──────────────────────────────────────────────────────────────────────

export function init() {
    modal = document.getElementById('reporter-ticket-comments-modal');
    if (!modal) { return; }

    titleEl     = document.getElementById('rep-comments-modal-title');
    bodyEl      = modal.querySelector('[data-comments-modal-body]');
    form        = modal.querySelector('[data-comments-modal-form]');
    noCommentEl = modal.querySelector('[data-comments-modal-no-comment]');
    detailLink  = modal.querySelector('[data-comments-modal-detail-link]');
    textarea    = modal.querySelector('[data-comments-modal-input]');
    submitBtn   = modal.querySelector('[data-comments-modal-submit]');
    errorEl     = modal.querySelector('[data-comments-modal-error]');

    // Close on overlay and × button (both carry data-comments-modal-close)
    modal.addEventListener('click', (e) => {
        if (e.target.closest('[data-comments-modal-close]')) { close(); }
    });

    // Escape key closes
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) { close(); }
    });

    // Focus trap
    modal.addEventListener('keydown', trapFocus);

    // Form submit
    if (form) { form.addEventListener('submit', handleFormSubmit); }

    // Trigger clicks (delegated — works for any cards already in the DOM)
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-reporter-ticket-comments-trigger]');
        if (!trigger) { return; }
        e.preventDefault();

        activeTrigger = trigger;
        trigger.setAttribute('aria-expanded', 'true');

        const url       = trigger.dataset.commentsUrl || '';
        const storeUrl  = trigger.dataset.commentsStoreUrl || '';
        const ref       = trigger.dataset.ticketRef || '';
        const article   = trigger.closest('article');
        const detailUrl = article?.querySelector('a.rep-btn')?.href || '';

        open();
        loadComments(url, storeUrl, ref, detailUrl);
    });
}
