import { initializeApp } from '@firebase/app';
import { getMessaging, getToken, onMessage } from '@firebase/messaging';

window.sbProps.firebaseApps.forEach((app) => {
    if (app.publicConfiguration && app.webPushPublicKey && app.appIdentifier) {
        const firebaseApp = initializeApp(app.publicConfiguration);

        // Ask for notification permission I guess?
        if ('Notification' in window) {
            // Register I guess?
            const messaging = getMessaging(firebaseApp);

            // Receipt of a message
            onMessage(messaging, (payload) => {
                console.log('Message received. ', payload);
                const notificationOptions = {
                    body: payload.notification.body,
                    icon: "/images/logo.php?logo=skin_logo_square&size=apple72",
                    image: payload.notification.icon ?? "/images/logo.php?logo=skin_logo_square&size=apple72",
                    badge: "/images/logo.php?logo=skin_logo_square&size=apple72",
                    renotify: false,
                };
                if (payload.notification.timestamp !== '0') {
                    notificationOptions.timestamp = payload.notification.timestamp;
                }
                let notif = new Notification(payload.notification.title, notificationOptions);
                notif.onclick = (event) => {
                    // debug log?
                    console.log("On notification click: .ts");
                    event.preventDefault(); // prevent the browser from focusing the Notification's tab
                    // some notifications have a href, some don't just load the dashboard for those missing hrefs.
                    const notifHref = payload.notification.href ?? "/";

                    // debug log?
                    console.log("opening: ", notifHref);

                    // open the notification destination
                    window.open(notifHref, "_blank");
                };
            });
        }
    }
});
