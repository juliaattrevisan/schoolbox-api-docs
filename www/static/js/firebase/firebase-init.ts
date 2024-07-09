import { initializeApp } from "@firebase/app";
import { getMessaging, getToken, deleteToken, onMessage } from "@firebase/messaging";

window.sbProps.firebaseApps.forEach((app) => {
    if (app.publicConfiguration && app.webPushPublicKey && app.appIdentifier) {
        const firebaseApp = initializeApp(app.publicConfiguration);

        // Check if the browser supports notifications
        if ("Notification" in window) {
            // Retrieve the current Firebase Messaging instance.
            const messaging = getMessaging(firebaseApp);

            // Receipt of a message
            onMessage(messaging, (payload) => {
                console.log("Message received. ", payload);

                const notificationOptions = {
                    body: payload.notification.body,
                    icon: "/images/logo.php?logo=skin_logo_square&size=apple72",
                    image: payload.notification.icon ?? "/images/logo.php?logo=skin_logo_square&size=apple72",
                    badge: "/images/logo.php?logo=skin_logo_square&size=apple72",
                    renotify: false,
                };
                if (payload.notification.timestamp !== "0") {
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
            getToken(messaging, { vapidKey: app.webPushPublicKey })
                .then(async (currentToken) => {
                    if (window.sbProps.pwaConfig.notificationState === "denied") {
                        // If notification blocked while requesting, should stop registering attempt
                        throw new Error("Notification blocked. Stop registering attempts.");
                    }

                    window.sbProps.pwaConfig.notificationState = "registering";
                    window.sbProps.pwaConfig.notificationToken = currentToken;

                    // Post this token to Schoolbox,
                    // registering it for the current user.
                    if (debug) {
                        console.log(`Current token: ${currentToken}`);
                    }

                    // The Firebase app may one day be a separate
                    // service to the other mobile apps: should that
                    // happen, the registration will be pushed to some
                    // other place.
                    // For now, just push it to the Firebase app.
                    const formData = new FormData();
                    formData.append("appId", app.appIdentifier);
                    formData.append("token", currentToken);
                    formData.append("deviceId", deviceId);

                    await fetch("/api/register/Firebase", {
                        method: "POST",
                        body: formData,
                    });

                    window.sbProps.pwaConfig.notificationState = "registered";
                })
                .catch((error) => {
                    if (debug) {
                        console.log(`Error on getting token: ${error}`);
                    }
                });
        }
    });
}

/**
 * Respond to a request to allow notifications, send both to firebase, and schoolbox
 * @param { CustomEvent } event - Information about the event.
 */
function denyNotif(event: CustomEvent) {
    const debug = event.detail.debug as boolean;
    window.sbProps.firebaseApps.forEach((app) => {
        if (app.publicConfiguration && app.webPushPublicKey && app.appIdentifier) {
            const firebaseApp = initializeApp(app.publicConfiguration);
            const messaging = getMessaging(firebaseApp);
            // Generate a new token, and store it in Schoolbox's
            // mobile app registration.
            getToken(messaging, { vapidKey: app.webPushPublicKey })
                .then(async (currentToken) => {
                    // delete the token from firebase
                    void deleteToken(messaging);
                    // delete the local cache token
                    window.sbProps.pwaConfig.notificationToken = null;
                    window.sbProps.pwaConfig.notificationState = "removing";

                    // Post this token to Schoolbox,
                    // registering it for the current user.
                    if (debug) {
                        console.log(`Current token: ${currentToken}`);
                    }

                    // The Firebase app may one day be a separate
                    // service to the other mobile apps: should that
                    // happen, the registration will be pushed to some
                    // other place.
                    // For now, just push it to the Firebase app.
                    await fetch("/api/register/Firebase", {
                        method: "DELETE",
                        body: JSON.stringify({
                            appId: app.appIdentifier,
                            token: currentToken,
                        }),
                        headers: {
                            "Content-Type": "application/json",
                        },
                    });

                    window.sbProps.pwaConfig.notificationState = "removed";
                })
                .catch((error) => {
                    if (debug) {
                        console.log(`Error on getting token: ${error}`);
                    }
                });
        }
    });
}

document.addEventListener("PWA_NOTIFICATION_ALLOW", (event) => {
    requestNotif(event as CustomEvent);
});

document.addEventListener("PWA_NOTIFICATION_DENY", (event) => {
    denyNotif(event as CustomEvent);
});
