importScripts('https://www.gstatic.com/firebasejs/12.7.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/12.7.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: 'AIzaSyBgzMo3I1MEQ_BlZUQy8rq8rfPl6NIUFTs',
  authDomain: 'tattoodesk-3390d.firebaseapp.com',
  projectId: 'tattoodesk-3390d',
  storageBucket: 'tattoodesk-3390d.firebasestorage.app',
  messagingSenderId: '391315089383',
  appId: '1:391315089383:web:9578be319024c273bfff42',
  measurementId: 'G-ME4PNK55YY',
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  const notification = payload.notification || {};
  const title = notification.title || 'Tattoodesk';
  const options = {
    body: notification.body || '',
    icon: '/favicon.ico',
    badge: '/favicon.ico',
    data: payload.data || {},
  };

  self.registration.showNotification(title, options);
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(clients.openWindow('/admin'));
});
