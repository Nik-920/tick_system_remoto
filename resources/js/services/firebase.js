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

export async function requestPermissionAndGetToken() {
    try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            console.warn('Permiso de notificaciones denegado.');
            return null;
        }

        // Registrar service worker y esperar a que esté activo
        const swRegistration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');

        // Esperar a que el SW esté activo
        await navigator.serviceWorker.ready;
        console.log('Service Worker activo:', swRegistration);

        const token = await getToken(messaging, {
            vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
            serviceWorkerRegistration: swRegistration,
        });

        if (token) {
            console.log('FCM Token obtenido:', token);
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
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        await fetch('/fcm-tokens', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ token, device: 'web' }),
        });
    } catch (error) {
        console.error('Error guardando token FCM:', error);
    }
}

export function onForegroundMessage(callback) {
    onMessage(messaging, (payload) => {
        callback(payload);
    });
}
