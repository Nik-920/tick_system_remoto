import './bootstrap';
import { escapeHtml } from './utils/escape';
import { init as initUploadGuard } from './upload-guard';
import { requestPermissionAndGetToken, onForegroundMessage } from './services/firebase';

// Layout module: sidebar toggle, theme, dropdowns (all authenticated pages)
if (document.querySelector('.admin-layout')) {
    import('./components/layout')
        .then((mod) => mod.init?.())
        .catch((err) => console.error('Error loading layout module', err));
}

// Firebase push notifications (solo usuarios autenticados)
if (document.querySelector('.admin-layout')) {
    window.addEventListener('load', async () => {
        try {
            const token = await requestPermissionAndGetToken();
            if (token) {
                console.info('FCM token registrado correctamente.');
            }

            onForegroundMessage((payload) => {
                const title = payload.notification?.title ?? 'Nueva notificación';
                const body  = payload.notification?.body  ?? '';
                const url   = payload.data?.url ?? null;
                const type  = payload.data?.type ?? 'info';

                const icon = type === 'ticket_created' ? '🎫' : '🔔';

                // Mostrar toast
                showToastNotification(title, body, url);

                // Agregar al dropdown de campanita
                if (typeof globalThis.addNotification === 'function') {
                    globalThis.addNotification(title, body, url, icon);
                }
            });
        } catch (err) {
            console.error('Error inicializando Firebase Messaging:', err);
        }
    });
}

// Toast notification para mensajes en primer plano
function showToastNotification(title, body, url = null) {
    // Sonido
    try {
        const audio = new Audio('/sounds/notification.mp3');
        audio.volume = 0.6;
        audio.play().catch((e) => { console.debug('Audio play interrumpted', e); });
    } catch (e) {
        console.warn('No se pudo reproducir el sonido de notificación:', e);
    }

    const toast = document.createElement('div');
    toast.className = 'fcm-toast';
    toast.innerHTML = `
        <div class="fcm-toast-icon">🔔</div>
        <div class="fcm-toast-content">
            <p class="fcm-toast-title">${escapeHtml(title)}</p>
            <p class="fcm-toast-body">${escapeHtml(body)}</p>
        </div>
        <button class="fcm-toast-close" onclick="this.parentElement.remove()">✕</button>
    `;

    if (url) {
        toast.style.cursor = 'pointer';
        toast.addEventListener('click', (e) => {
            if (!e.target.classList.contains('fcm-toast-close')) {
                globalThis.location.href = url;
            }
        });
    }

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('fcm-toast--hiding');
        setTimeout(() => toast.remove(), 400);
    }, 5000);
}

globalThis.showToastNotification = showToastNotification;

// Community social actions: optimistic UI for reactions + saves.
if (document.querySelector('[data-community-social-form]')) {
    import('./community-social-actions')
        .then((mod) => mod.init?.())
        .catch((err) => console.error('Error loading community-social-actions', err));
}

// Reporter tickets comments modal.
if (document.querySelector('[data-reporter-ticket-comments-trigger]')) {
    import('./reporter-ticket-comments-modal')
        .then((mod) => mod.init?.())
        .catch((err) => console.error('Error loading reporter-ticket-comments-modal', err));
}

// Community media fallback: hide broken img and show placeholder on error.
if (document.querySelector('[data-community-media-img]')) {
    import('./community-media-fallback')
        .then((mod) => mod.init?.())
        .catch((err) => console.error('Error loading community-media-fallback', err));
}

// Carga condicional de scripts por página
const pageLoaders = [
    { selector: '.tickets-create-page', loader: () => import('./pages/tickets-create') },
    { selector: '.ticket-show-page',    loader: () => import('./pages/tickets-show') },
    { selector: '.locations-index',     loader: () => import('./pages/locations') },
];

function runPageLoaders() {
    for (const { selector, loader } of pageLoaders) {
        if (document.querySelector(selector)) {
            loader()
                .then((mod) => {
                    if (mod && typeof mod.init === 'function') {
                        try { mod.init(); }
                        catch (e) { console.error('Error initializing page module', e); }
                    }
                })
                .catch((err) => console.error('Error loading page module', err));
            break;
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runPageLoaders, { once: true });
} else {
    runPageLoaders();
}

// Upload guard — file validation before submit (all pages with file inputs).
if (document.querySelector('[data-upload-guard]')) {
    initUploadGuard();
}

// Global Confirm Modal Logic
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('globalConfirmModal');
    if (!modal) return;

    const modalText = document.getElementById('globalConfirmModalText');
    const btnCancel = document.getElementById('globalConfirmModalCancel');
    const btnConfirm = document.getElementById('globalConfirmModalConfirm');
    const backdrop = document.getElementById('globalConfirmModalBackdrop');
    const inputWrap = document.getElementById('globalConfirmModalInputWrap');
    const inputField = document.getElementById('globalConfirmModalInput');
    const inputError = document.getElementById('globalConfirmModalInputError');

    let pendingForm = null;
    let isPrompt = false;
    let promptName = 'reason';

    function openModal(message, form, isPromptMode = false) {
        modalText.textContent = message;
        pendingForm = form;
        isPrompt = isPromptMode;
        
        if (isPrompt) {
            inputWrap.classList.remove('hidden');
            inputField.value = '';
            inputError.classList.add('hidden');
            // Allow forms to specify the input name, default to 'reason'
            promptName = form.dataset.promptName || 'reason';
            setTimeout(() => inputField.focus(), 50);
        } else {
            inputWrap.classList.add('hidden');
        }

        modal.classList.remove('hidden');
        // trigger reflow (function call, not a bare property read) so the
        // opacity change below transitions instead of jumping
        modal.getBoundingClientRect();
        modal.classList.remove('opacity-0');
    }

    function closeModal() {
        modal.classList.add('opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
            pendingForm = null;
            isPrompt = false;
        }, 300);
    }

    btnCancel.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);

    btnConfirm.addEventListener('click', () => {
        if (!pendingForm) return;

        if (isPrompt) {
            const val = inputField.value.trim();
            if (!val) {
                inputError.classList.remove('hidden');
                inputField.focus();
                return;
            }
            
            // Create hidden input or update existing
            let hiddenInput = pendingForm.querySelector(`input[name="${promptName}"]`);
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = promptName;
                pendingForm.appendChild(hiddenInput);
            }
            hiddenInput.value = val;
            delete pendingForm.dataset.prompt;
        } else {
            delete pendingForm.dataset.confirm;
        }

        // Use requestSubmit to fire submit events (so button disabling scripts still run)
        if (pendingForm.requestSubmit) {
            pendingForm.requestSubmit();
        } else {
            pendingForm.submit();
        }
        
        closeModal();
    });

    // Handle Enter key inside the prompt input
    inputField?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            btnConfirm.click();
        }
    });

    // Intercept form submissions globally
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form?.dataset.confirm) {
            e.preventDefault();
            openModal(form.dataset.confirm, form, false);
        } else if (form?.dataset.prompt) {
            e.preventDefault();
            openModal(form.dataset.prompt, form, true);
        }
    });
});
