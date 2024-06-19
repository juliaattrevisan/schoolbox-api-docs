import { initializeApp } from '@firebase/app';
import { getMessaging, getToken, deleteToken, onMessage } from '@firebase/messaging';

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

/**
 * Respond to a request to allow notifications, send both to firebase, and schoolbox
 *
 * @param { CustomEvent } event - Information about the event.
 */
function requestNotif(event: CustomEvent) {
    const debug = event.detail.debug as boolean;
    const deviceId = event.detail.deviceId as string;
    window.sbProps.firebaseApps.forEach((app) => {
        if (app.publicConfiguration && app.webPushPublicKey && app.appIdentifier) {
            const firebaseApp = initializeApp(app.publicConfiguration);
            const messaging = getMessaging(firebaseApp);
            // Generate a new token, and store it in Schoolbox's
            // mobile app registration.
            getToken(messaging, {vapidKey: app.webPushPublicKey})
                .then((currentToken) => {
                    if('denied' === window.localStorage.getItem("notification")) {
                        //If notification blocked while requesting, should stop registering attempt
                        throw new Error('Notification blocked. Stop registering attempts.');
                    }
                    window.localStorage.setItem("notification", "registering");
                    window.localStorage.setItem("notifToken", currentToken);
                    // Post this token to Schoolbox,
                    // registering it for the current user.
                    if (debug) {
                        console.log('Current token: ' + currentToken);
                    }

                    // The Firebase app may one day be a separate
                    // service to the other mobile apps: should that
                    // happen, the registration will be pushed to some
                    // other place.
                    // For now, just push it to the Firebase app.
                    let formData = new FormData();
                    formData.append('appId', app.appIdentifier);
                    formData.append('token', currentToken);
                    formData.append('deviceId', deviceId);
                    fetch('/api/register/Firebase', {
                        method: 'POST',
                        body: formData,
                    }).then((response) => {
                        window.localStorage.setItem("notification", "registered");
                    })

                })
                .catch((error) => {
                    if (debug) {
                        console.log('Error on getting token: ' + error);
                    }
                });
        }
    });
}

/**
 * Respond to a request to allow notifications, send both to firebase, and schoolbox
 *
 * @param { CustomEvent } event - Information about the event.
 */
function denyNotif(event: CustomEvent) {
    console.log('teststsdsdsdsds');
    const debug = event.detail.debug as boolean;
    window.sbProps.firebaseApps.forEach((app) => {
        if (app.publicConfiguration && app.webPushPublicKey && app.appIdentifier) {
            const firebaseApp = initializeApp(app.publicConfiguration);
            const messaging = getMessaging(firebaseApp);
            // Generate a new token, and store it in Schoolbox's
            // mobile app registration.
            getToken(messaging, {vapidKey: app.webPushPublicKey})
                .then((currentToken) => {
                    //delete the token from firebase
                    deleteToken(messaging)
                    // delete the local cache token
                    window.localStorage.removeItem("notifToken");
                    window.localStorage.setItem("notification", "removing");

                    // Post this token to Schoolbox,
                    // registering it for the current user.
                    if (debug) {
                        console.log('Current token: ' + currentToken);
                    }

                    // The Firebase app may one day be a separate
                    // service to the other mobile apps: should that
                    // happen, the registration will be pushed to some
                    // other place.
                    // For now, just push it to the Firebase app.
                    let formData = new FormData();
                    formData.append('appId', app.appIdentifier);
                    formData.append('token', currentToken);
                    fetch('/api/register/Firebase', {
                        method: 'DELETE',
                        body: formData,
                    }).then((response) => {
                        window.localStorage.setItem("notification", "removed");
                    });
                })
                .catch((error) => {
                    if (debug) {
                        console.log('Error on getting token: ' + error);
                    }
                });
        }
    });
}

document.addEventListener("PWA_NOTIFICATION_ALLOW", (event) => {
    requestNotif(event as CustomEvent);
});

document.addEventListener("PWA_NOTIFICATION_DENY", (event) => {
    console.log('testststst');
    denyNotif(event as CustomEvent);
});
