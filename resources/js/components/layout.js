/**
 * Layout Module — Sidebar toggle, theme switch, dropdowns, notificaciones
 * Loaded on every authenticated page via app.js
 */

const logStorageError = (error) => {
    console.debug('Storage access failed', error);
};

const safeStorageRead = (reader, fallback = null) => {
    try {
        return reader();
    } catch (error) {
        logStorageError(error);
        return fallback;
    }
};

const safeStorageWrite = (writer) => {
    try {
        writer();
    } catch (error) {
        logStorageError(error);
    }
};

export function init() {
    const layout    = document.getElementById('adminLayout');
    const sidebar   = document.getElementById('adminSidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const closeBtn  = document.getElementById('sidebarCloseBtn');
    const themeBtn  = document.getElementById('themeToggleBtn');

    if (!layout || !sidebar) return;

    toggleBtn?.addEventListener('click', () => {
        if (isMobile()) {
            openMobileSidebar();
        } else {
            layout.classList.toggle('sidebar-collapsed');
            saveSidebarState();
        }
    });

    closeBtn?.addEventListener('click', closeMobileSidebar);
    overlay?.addEventListener('click', closeMobileSidebar);

    const storedSidebar = safeStorageRead(() => localStorage.getItem('tick-sidebar'), null);
    if (!isMobile() && storedSidebar === 'collapsed') {
        layout.classList.add('sidebar-collapsed');
    }

    function openMobileSidebar() {
        sidebar.classList.add('sidebar-open');
        overlay?.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileSidebar() {
        sidebar.classList.remove('sidebar-open');
        overlay?.classList.remove('active');
        document.body.style.overflow = '';
    }

    function saveSidebarState() {
        safeStorageWrite(() => {
            localStorage.setItem(
                'tick-sidebar',
                layout.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded'
            );
        });
    }

    function isMobile() {
        return (globalThis.innerWidth ?? 0) < 768;
    }

    globalThis.addEventListener('resize', () => {
        if (!isMobile() && sidebar.classList.contains('sidebar-open')) {
            closeMobileSidebar();
        }
    });

    // ── Theme Management ──────────────────────────────────────────
    initTheme();

    function getStoredTheme() {
        const ls = safeStorageRead(() => localStorage.getItem('tick-theme'), null);
        if (ls === 'light' || ls === 'dark') return ls;
        // Fallback: cookie
        const match = /(?:^|;\s*)tick-theme=(light|dark)/.exec(document.cookie);
        return match ? match[1] : null;
    }

    function setStoredTheme(theme) {
        safeStorageWrite(() => localStorage.setItem('tick-theme', theme));
        document.cookie = `tick-theme=${theme};path=/;max-age=31536000;SameSite=Lax`;
    }

    function getPreferredTheme() {
        const prefersDark = globalThis.matchMedia?.('(prefers-color-scheme: dark)')?.matches;
        return prefersDark ? 'dark' : 'light';
    }

    function updateThemeButton(theme) {
        const btn = document.getElementById('themeToggleBtn');
        if (!btn) return;
        btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    }

    function applyTheme(theme) {
        document.documentElement.dataset.theme = theme;
        updateThemeButton(theme);
    }

    function toggleTheme() {
        const current = document.documentElement.dataset.theme || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        setStoredTheme(next);
    }

    function initTheme() {
        // Ensure the correct theme is applied (may already be set by the <head> boot script)
        const stored = getStoredTheme();
        const theme  = stored ?? getPreferredTheme();
        applyTheme(theme);

        // Wire up the toggle button
        themeBtn?.addEventListener('click', toggleTheme);

        // Listen for OS-level preference changes ONLY when the user hasn't manually chosen
        globalThis.matchMedia?.('(prefers-color-scheme: dark)')?.addEventListener('change', (e) => {
            if (!getStoredTheme()) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    document.addEventListener('click', (e) => {
        document.querySelectorAll('details.topbar-user-menu[open]').forEach((d) => {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });

        const notifWrapper  = document.getElementById('notifWrapper');
        const notifDropdown = document.getElementById('notifDropdown');
        if (notifWrapper && notifDropdown && !notifWrapper.contains(e.target)) {
            notifDropdown.style.display = 'none';
            notifDropdown.setAttribute('aria-hidden', 'true');
            const notifBtn = document.getElementById('notifBtn');
            notifBtn?.setAttribute('aria-expanded', 'false');
        }
    });

    initNotifications();
}

function normalizeIcon(icon) {
    const text = (icon ?? '🔔').toString().trim();
    if (!text) return '🔔';
    return Array.from(text)[0] ?? '🔔';
}

function stripLeadingIcon(title, iconGlyph) {
    const raw = (title ?? '').toString();
    const trimmed = raw.trimStart();
    if (!trimmed) return raw;
    const firstGlyph = Array.from(trimmed)[0];
    if (firstGlyph && firstGlyph === iconGlyph) {
        return trimmed.slice(firstGlyph.length).trimStart();
    }
    return raw;
}

function initNotifications() {
    const notifBtn      = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifBadge    = document.getElementById('notifBadge');
    const notifList     = document.getElementById('notifList');
    const notifMarkAll  = document.getElementById('notifMarkAll');

    if (!notifBtn || !notifDropdown) return;

    let notifications   = [];
    let notifLoaded     = false;
    let fetchInFlight   = null;

    async function fetchAndRender() {
        await fetchNotifications();
        renderNotifications();
    }

    async function fetchNotifications() {
        if (fetchInFlight) return fetchInFlight;
        fetchInFlight = (async () => {
            try {
                const res  = await fetch('/notifications', {
                    headers: {
                        'Accept':            'application/json',
                        'X-Requested-With':  'XMLHttpRequest',
                    }
                });
                const data = await res.json();
                notifications = data.notifications ?? [];
                updateBadge(data.unread_count ?? 0);
                notifLoaded = true;
            } catch (e) {
                console.error('Error cargando notificaciones:', e);
            } finally {
                fetchInFlight = null;
            }
        })();
        return fetchInFlight;
    }

    function updateBadge(count) {
        if (count > 0) {
            notifBadge.style.display = 'flex';
            notifBadge.textContent   = count > 99 ? '99+' : count;
        } else {
            notifBadge.style.display = 'none';
        }
        setMarkAllState(count);
    }

    function setMarkAllState(count) {
        if (!notifMarkAll) return;
        const disabled = count === 0;
        notifMarkAll.disabled = disabled;
        notifMarkAll.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    }

    function renderNotifications() {
        if (notifications.length === 0) {
            notifList.innerHTML = `
                <div class="notif-empty" role="status">
                    <span class="notif-empty-icon" aria-hidden="true">🔔</span>
                    <p class="notif-empty-title">No tienes notificaciones</p>
                    <p class="notif-empty-subtitle">Las actualizaciones de tus tickets aparecerán aquí.</p>
                </div>
            `;
            return;
        }

        notifList.innerHTML = notifications.map(n => {
        const iconGlyph = normalizeIcon(n.icon);
        const safeTitle = stripLeadingIcon(n.title, iconGlyph);
        return `
        <div class="notif-item ${n.read_at ? 'notif-item--read' : 'notif-item--unread'}" data-id="${n.id}" role="listitem">
            <a class="notif-item-link" href="${n.url || '#'}">
                <span class="notif-item-icon-wrap" aria-hidden="true">
                    <span class="notif-item-icon">${iconGlyph}</span>
                </span>
                <div class="notif-item-content">
                    <p class="notif-item-title">${safeTitle}</p>
                    <p class="notif-item-body">${n.body}</p>
                    <span class="notif-item-time">${n.time || ''}</span>
                </div>
            </a>
            ${n.read_at ? '<span class="notif-item-read-label">Leído</span>' : `
            <button class="notif-item-read-btn" type="button" data-id="${n.id}" title="Marcar como leída" aria-label="Marcar notificación como leída">
                ✓
            </button>`}
        </div>
    `;
        }).join('');

        // Click en botón marcar leído
        notifList.querySelectorAll('.notif-item-read-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                const id = btn.dataset.id;
                await markAsRead(id);
            });
        });
    }

    async function markAsRead(id) {
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept':       'application/json',
                }
            });
            // Update locally: no re-fetch needed
            const now = new Date().toISOString();
            notifications = notifications.map(n => n.id === id ? { ...n, read_at: now } : n);
            const unread = notifications.filter(n => !n.read_at).length;
            updateBadge(unread);
            renderNotifications();
        } catch (e) {
            console.error('Error marcando notificación:', e);
        }
    }

    async function markAllAsRead() {
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch('/notifications/read-all', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept':       'application/json',
                }
            });
            // Update locally: no re-fetch needed
            const now = new Date().toISOString();
            notifications = notifications.map(n => ({ ...n, read_at: n.read_at ?? now }));
            updateBadge(0);
            renderNotifications();
        } catch (e) {
            console.error('Error marcando todas:', e);
        }
    }

    notifBtn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const isVisible = notifDropdown.style.display === 'block';
        notifDropdown.style.display = isVisible ? 'none' : 'block';
        notifDropdown.setAttribute('aria-hidden', isVisible ? 'true' : 'false');
        notifBtn.setAttribute('aria-expanded', isVisible ? 'false' : 'true');
        if (!isVisible) {
            if (notifLoaded) {
                renderNotifications();
            } else {
                await fetchAndRender();
            }
        }
    });

    notifMarkAll?.addEventListener('click', async () => {
        if (notifMarkAll.disabled) return;
        await markAllAsRead();
    });

    // Cargar solo el badge al inicio (sin renderizar el dropdown)
    fetchNotifications();

    // Polling cada 60 segundos (solo badge, no renderiza a menos que el dropdown esté abierto)
    setInterval(async () => {
        await fetchNotifications();
        if (notifDropdown.style.display === 'block') {
            renderNotifications();
        }
    }, 60000);

    // Exponer función global para agregar desde Firebase en tiempo real
    globalThis.addNotification = function(title, body, url = null, icon = '🔔') {
        notifications.unshift({
            id:      Date.now().toString(),
            title,
            body,
            url,
            icon,
            read_at: null,
            time:    'ahora',
        });
        updateBadge(notifications.filter(n => !n.read_at).length);
        if (notifDropdown.style.display === 'block') {
            renderNotifications();
        }
    };
}
