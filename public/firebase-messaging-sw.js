importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey:            'AIzaSyB0yjY0Qj_M2ePnpDNNXC-HtxixxU5ze_Q',
    authDomain:        'tick-system-6cde2.firebaseapp.com',
    projectId:         'tick-system-6cde2',
    storageBucket:     'tick-system-6cde2.firebasestorage.app',
    messagingSenderId: '205770110649',
    appId:             '1:205770110649:web:1203a0a60289b4eee34d68',
});

const messaging = firebase.messaging();

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
