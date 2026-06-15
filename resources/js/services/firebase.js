import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, onMessage } from 'firebase/messaging';

const firebaseConfig = {
    apiKey:            import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain:        import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId:         import.meta.env.VITE_FIREBASE_PROJECT_ID,
    storageBucket:     import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId:             import.meta.env.VITE_FIREBASE_APP_ID,
};

const app = initializeApp(firebaseConfig);
const messaging = getMessaging(app);

const FCM_TOKEN_STORAGE_KEY = 'tick-fcm-token';

export async function requestPermissionAndGetToken() {
    try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            console.warn('Permiso de notificaciones denegado.');
            return null;
        }

        // Register SW without exposing config in the URL query string.
        // Config is passed via postMessage after the SW is active.
        const swRegistration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');

        await navigator.serviceWorker.ready;

        // Send config to the active SW so it can initialize Firebase internally.
        const target = swRegistration.active ?? swRegistration.installing ?? swRegistration.waiting;
        target?.postMessage({ type: 'FIREBASE_CONFIG', config: firebaseConfig });

        const token = await getToken(messaging, {
            vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
            serviceWorkerRegistration: swRegistration,
        });

        if (token) {
            await saveTokenToServer(token);
            return token;
        }

        return null;
    } catch (error) {
        console.error('Error obteniendo token FCM:', error);
        return null;
    }
}

async function saveTokenToServer(token) {
    try {
        // Skip the server round-trip when the same token is already registered.
        const stored = localStorage.getItem(FCM_TOKEN_STORAGE_KEY);
        if (stored === token) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch('/fcm-tokens', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ token, device: 'web' }),
        });

        if (res.ok) {
            localStorage.setItem(FCM_TOKEN_STORAGE_KEY, token);
        }
    } catch (error) {
        console.error('Error guardando token FCM:', error);
    }
}

export function onForegroundMessage(callback) {
    onMessage(messaging, (payload) => {
        callback(payload);
    });
}
