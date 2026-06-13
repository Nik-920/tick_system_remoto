importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

let messaging = null;

// Accept Firebase config via postMessage so we avoid leaking it in the SW URL.
self.addEventListener('message', (event) => {
    if (!event.data || event.data.type !== 'FIREBASE_CONFIG') return;
    if (messaging) return; // already initialized

    firebase.initializeApp(event.data.config);
    messaging = firebase.messaging();

    messaging.onBackgroundMessage((payload) => {
        const title = payload.data?.title ?? 'Nueva notificación';
        const body  = payload.data?.body  ?? '';

        self.registration.showNotification(title, {
            body:  body,
            icon:  '/incidencias.png',
            badge: '/incidencias.png',
            data:  payload.data ?? {},
        });
    });
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url ?? '/dashboard';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
