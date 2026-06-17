import { initKebabDropdowns } from '../components/dropdown';

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

export default { init };
