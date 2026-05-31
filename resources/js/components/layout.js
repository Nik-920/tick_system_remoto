/**
 * Layout Module — Sidebar toggle, theme switch, dropdowns, notificaciones
 * Loaded on every authenticated page via app.js
 */

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

    if (!isMobile() && localStorage.getItem('tick-sidebar') === 'collapsed') {
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
        localStorage.setItem(
            'tick-sidebar',
            layout.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded'
        );
    }

    function isMobile() {
        return window.innerWidth < 768;
    }

    window.addEventListener('resize', () => {
        if (!isMobile() && sidebar.classList.contains('sidebar-open')) {
            closeMobileSidebar();
        }
    });

    // ── Theme Management ──────────────────────────────────────────
    initTheme();

    function getStoredTheme() {
        try {
            const ls = localStorage.getItem('tick-theme');
            if (ls === 'light' || ls === 'dark') return ls;
        } catch (_) {}
        // Fallback: cookie
        const m = document.cookie.match(/(?:^|;\s*)tick-theme=(light|dark)/);
        return m ? m[1] : null;
    }

    function setStoredTheme(theme) {
        try { localStorage.setItem('tick-theme', theme); } catch (_) {}
        document.cookie = `tick-theme=${theme};path=/;max-age=31536000;SameSite=Lax`;
    }

    function getPreferredTheme() {
        return (window.matchMedia?.('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    }

    function updateThemeButton(theme) {
        const btn = document.getElementById('themeToggleBtn');
        if (!btn) return;
        btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        updateThemeButton(theme);
    }

    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
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
        window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
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

function initNotifications() {
    const notifBtn      = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifBadge    = document.getElementById('notifBadge');
    const notifList     = document.getElementById('notifList');
    const notifMarkAll  = document.getElementById('notifMarkAll');

    if (!notifBtn || !notifDropdown) return;

    let notifications = [];

    async function fetchAndRender() {
        await fetchNotifications();
        renderNotifications();
    }

    async function fetchNotifications() {
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
        } catch (e) {
            console.error('Error cargando notificaciones:', e);
        }
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
                await fetchAndRender();
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
            await fetchAndRender();
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
            await fetchAndRender();
        }
    });

    notifMarkAll?.addEventListener('click', async () => {
        if (notifMarkAll.disabled) return;
        await markAllAsRead();
    });

    // Cargar badge al inicio
    fetchNotifications();

    // Polling cada 30 segundos
    setInterval(async () => {
        await fetchNotifications();
    }, 30000);

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
        renderNotifications();
    };
}
