import { initializeApp } from '@firebase/app';

const firebaseConfig = window.sbProps.firebaseConfig ?? null;
if (firebaseConfig) {
    window.firebaseApp = initializeApp(firebaseConfig);
}
