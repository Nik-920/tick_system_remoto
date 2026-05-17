import './bootstrap';
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
        console.debug('No se pudo reproducir el sonido de notificación');
    }

    const toast = document.createElement('div');
    toast.className = 'fcm-toast';
    toast.innerHTML = `
        <div class="fcm-toast-icon">🔔</div>
        <div class="fcm-toast-content">
            <p class="fcm-toast-title">${title}</p>
            <p class="fcm-toast-body">${body}</p>
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

// Carga condicional de scripts por página
const pageLoaders = [
    { selector: '.users-page',    loader: () => import('./pages/users') },
    { selector: '.tickets-page',  loader: () => import('./pages/tickets') },
    { selector: '.locations-page',loader: () => import('./pages/locations') },
    { selector: '.welcome-hero',  loader: () => import('./pages/welcome') },
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
