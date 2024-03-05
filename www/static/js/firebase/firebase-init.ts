import { initializeApp } from '@firebase/app';

const firebaseConfig = window.sbProps.firebaseConfig ?? null;
if (firebaseConfig) {
    window.firebaseApp = initializeApp(firebaseConfig);

    // Ask for notification permission I guess?
    if ('Notification' in window) {
        window.Notification.requestPermission().then((permission) => {
            if (permission === 'granted') {
                console.info('Notification permission granted');
            } else if (permission === 'denied') {
                console.warn('Notification permission denied');
            }
        });
    }
}
