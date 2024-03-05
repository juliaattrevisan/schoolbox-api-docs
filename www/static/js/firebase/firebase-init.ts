import { initializeApp } from '@firebase/app';
import { getMessaging, getToken } from '@firebase/messaging';

const firebaseConfig = window.sbProps.firebaseConfig ?? null;
const firebaseWebPushPublicKey = window.sbProps.firebaseWebPushPublicKey ?? null;
const firebaseAppIdentifiers = window.sbProps.firebaseAppIdentifiers ?? null;
if (firebaseConfig && firebaseWebPushPublicKey && firebaseAppIdentifiers) {
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
                        // Post this token to Schoolbox,
                        // registering it for the current user.
                        console.log('Current token: ' + currentToken);

                        // The Firebase app will likely be a separate service to
                        // the other mobile apps: should that happen, the
                        // registration will be pushed to some other place.
                        // For now, just push it to the Firebase apps, if there
                        // are any.
                        firebaseAppIdentifiers.forEach((appIdentifier) => {
                            let formData = new FormData();
                            formData.append('appId', appIdentifier);
                            formData.append('token', currentToken);
                            fetch('/api/register/Firebase', {
                                method: 'POST',
                                body: formData,
                            });
                        });
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
