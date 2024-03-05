import { initializeApp } from '@firebase/app';
import { getMessaging, getToken } from '@firebase/messaging';

const firebaseConfig = window.sbProps.firebaseConfig ?? null;
const firebaseWebPushPublicKey = window.sbProps.firebaseWebPushPublicKey ?? null;
if (firebaseConfig && firebaseWebPushPublicKey) {
    const firebaseApp = initializeApp(firebaseConfig);

    // Ask for notification permission I guess?
    if ('Notification' in window) {
        window.Notification.requestPermission().then((permission) => {
            if (permission === 'granted') {
                console.info('Notification permission granted');

                // Register I guess?
                const messaging = getMessaging(firebaseApp);

                // Generate a new token, and store it in Schoolbox I guess?
                // Should this be proxied through Schoolbox, though?
                getToken(messaging, { vapidKey: firebaseWebPushPublicKey })
                    .then((currentToken) => {
                        // Post this token to Schoolbox, registering it within
                        console.log('Current token: ' + currentToken);
                    })
                    .catch((error) => {
                        console.log('Error on getting token: ' + error);
                    });
            } else if (permission === 'denied') {
                console.warn('Notification permission denied');
            }
        });
    }
}
