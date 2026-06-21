export function init() {
    document.querySelectorAll('[data-community-media-img]').forEach((img) => {
        img.addEventListener('error', () => {
            img.hidden = true;
            const fallback = img
                .closest('[data-community-media-frame]')
                ?.querySelector('[data-community-media-fallback]');
            if (fallback) {
                fallback.hidden = false;
                fallback.removeAttribute('aria-hidden');
            }
        });
    });
}
