/**
 * Give the service worker access to Firebase Messaging.
 * Note that you can only use Firebase Messaging here. Other Firebase libraries
 * are not available in the service worker.
 **/
importScripts("https://www.gstatic.com/firebasejs/10.9.0/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/10.9.0/firebase-messaging-compat.js");

/**
 * Initialize the Firebase app in the service worker by passing in
 * your app's Firebase config object.
 * https://firebase.google.com/docs/web/setup#config-object
 */
firebase.initializeApp({
    apiKey: "AIzaSyB8JJPC5E1jtAJJ9X6WQgLxfyowHYMDX-0",
    authDomain: "quickstartjsmessaging.firebaseapp.com",
    projectId: "quickstartjsmessaging",
    storageBucket: "quickstartjsmessaging.appspot.com",
    messagingSenderId: "886544748781",
    appId: "1:886544748781:web:4afe61c9c430d769977ce4",
    measurementId: "G-C2PD04L4N3",
});

const messaging = firebase.messaging(); // Retrieve an instance of Firebase Messaging so that it can handle background messages.

/**
 * If you would like to customize notifications that are received in the
 * background (Web app is closed or not in browser focus) then you should
 * implement this optional method.
 * Keep in mind that FCM will still show notification messages automatically
 * and you should use data messages for custom notifications.
 * For more info see:
 * https://firebase.google.com/docs/cloud-messaging/concept-options
 */
messaging.onBackgroundMessage(function (payload) {
    console.warn("[firebase-messaging-sw.js] Received background message ", payload);
    // Customize notification here
    const notificationTitle = "Background Message Title";
    const notificationOptions = {
        body: "Background Message body.",
        icon: "/firebase-logo.png",
    };
    self.registration.showNotification(notificationTitle, notificationOptions);
});
