/**
 * Generic kebab/action dropdown
 *
 * Markup contract:
 *   <div data-dropdown>
 *     <button data-dropdown-trigger aria-expanded="false">⋮</button>
 *     <ul data-dropdown-menu hidden>...</ul>
 *   </div>
 *
 * Call once per page that contains [data-dropdown] elements.
 * Handles click-outside and Escape to close.
 */
export function initKebabDropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach((container) => {
        const trigger = container.querySelector('[data-dropdown-trigger]');
        const menu    = container.querySelector('[data-dropdown-menu]');
        if (!trigger || !menu) return;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = !menu.hidden;

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

    document.addEventListener('click', () => {
        document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => {
            menu.hidden = true;
            const trigger = menu.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => {
            menu.hidden = true;
            const trigger = menu.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]');
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus();
            }
        });
    });
}
