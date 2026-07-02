/**
 * community-comments-load-more.js
 *
 * "Ver más comentarios" — clicking the button fetches the next page of root
 * comments for that one ticket and appends them to the list.
 *
 * The response HTML is rendered server-side by Blade (comment-list-item.blade.php,
 * the exact same partial used for the comments already on the page) with all
 * user content already escaped via {{ }} — so it carries the same trust level
 * as the rest of the page's own markup and inserting it via insertAdjacentHTML
 * does not reopen the "never use innerHTML for user content" concern that
 * applies to raw, unescaped API payloads.
 *
 * This control has no non-JS fallback: it mirrors the existing
 * reporter-ticket-comments-modal trigger, a plain <button type="button">
 * that does nothing without JS. Same accepted tradeoff, same precedent.
 */

function getCsrfToken() {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
        document.querySelector('input[name="_token"]')?.value ??
        ''
    );
}

async function fetchMore(url, offset) {
    const requestUrl = new URL(url, window.location.origin);
    requestUrl.searchParams.set('offset', String(offset));

    const res = await fetch(requestUrl, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
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

function handleLoadMoreClick(e) {
    const btn = e.target.closest('[data-community-comments-more]');
    if (!btn || btn.dataset.loading === 'true') return;

    const list = btn.parentElement?.querySelector('[data-community-comments-list]');
    if (!list) return;

    const url = btn.dataset.url;
    const offset = parseInt(btn.dataset.offset, 10) || 0;
    const originalLabel = btn.innerHTML;

    btn.dataset.loading = 'true';
    btn.disabled = true;
    btn.textContent = 'Cargando…';

    fetchMore(url, offset)
        .then((data) => {
            list.insertAdjacentHTML('beforeend', data.html);

            if (data.has_more) {
                btn.dataset.offset = String(data.next_offset);
                btn.disabled = false;
                btn.innerHTML = originalLabel;
            } else {
                btn.remove();
            }
        })
        .catch(() => {
            btn.innerHTML = originalLabel;
            btn.disabled = false;
        })
        .finally(() => {
            btn.dataset.loading = 'false';
        });
}

export function init() {
    document.addEventListener('click', handleLoadMoreClick);
}
