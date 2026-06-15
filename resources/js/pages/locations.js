export function init() {
    initAdvancedFiltersToggle();
    initKebabDropdowns();
}

function initAdvancedFiltersToggle() {
    const toggle = document.getElementById('loc-adv-toggle');
    const panel  = document.getElementById('loc-adv-panel');
    if (!toggle || !panel) return;

    toggle.addEventListener('click', () => {
        const isOpen = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!isOpen));
        if (isOpen) {
            panel.hidden = true;
            panel.classList.remove('is-open');
        } else {
            panel.hidden = false;
            panel.classList.add('is-open');
        }
    });
}

function initKebabDropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach((container) => {
        const trigger = container.querySelector('[data-dropdown-trigger]');
        const menu    = container.querySelector('[data-dropdown-menu]');
        if (!trigger || !menu) return;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = !menu.hidden;

            // Close all other open menus first
            document.querySelectorAll('[data-dropdown-menu]').forEach((m) => {
                if (m !== menu) {
                    m.hidden = true;
                    const t = m.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]');
                    if (t) t.setAttribute('aria-expanded', 'false');
                }
            });

            menu.hidden = isOpen;
            trigger.setAttribute('aria-expanded', String(!isOpen));
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', () => {
        document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => {
            menu.hidden = true;
            const trigger = menu.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => {
                menu.hidden = true;
                const trigger = menu.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]');
                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'false');
                    trigger.focus();
                }
            });
        }
    });
}

export default { init };
