/**
 * Layout Module — Sidebar toggle, theme switch, dropdowns
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

    /* ─── Sidebar Toggle ─── */
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

    // Restore sidebar state on desktop
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

    // Close mobile sidebar on resize to desktop
    window.addEventListener('resize', () => {
        if (!isMobile() && sidebar.classList.contains('sidebar-open')) {
            closeMobileSidebar();
        }
    });

    /* ─── Theme Toggle ─── */
    themeBtn?.addEventListener('click', () => {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('tick-theme', next);
    });

    /* ─── Close dropdowns on outside click ─── */
    document.addEventListener('click', (e) => {
        document.querySelectorAll('details.topbar-user-menu[open]').forEach((d) => {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });
}
