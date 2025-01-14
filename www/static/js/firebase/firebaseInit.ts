import { initializeApp } from "@firebase/app";
import { getMessaging, getToken, deleteToken, onMessage } from "@firebase/messaging";

// See /library/Alaress/Async/Notification/Cloud/Firebase/Service.php::propagate
interface SbxFirebaseNotificationPayload {
    notification:
        | {
              title: string;
              body: string;
              icon: string;
              timestamp: number;
              href: string;
              unread: number;
              meta: unknown; // string|object
          }
        | undefined;
    fcm_options: {
        link: string;
    };
}

// Additions to the NotificationOptions interface, as the default NotificationOptions
// appears to differ from https://developer.mozilla.org/en-US/docs/Web/API/Notification/Notification#options
interface SbxNotificationOptions extends NotificationOptions {
    timestamp?: number;
    image?: string;
    renotify?: boolean;
}

window.sbProps.firebaseApps.forEach((app) => {
    if (app.publicConfiguration && app.webPushPublicKey && app.appIdentifier) {
        const firebaseApp = initializeApp(app.publicConfiguration);

        // Check if the browser supports notifications
        if ("Notification" in window) {
            // Retrieve the current Firebase Messaging instance.
            const messaging = getMessaging(firebaseApp);

            // Receipt of a message
            onMessage(messaging, (payload) => {
                const notificationPayload = (payload as unknown as SbxFirebaseNotificationPayload).notification; // We don't use firebase's standard payload format
                if (notificationPayload === undefined) {
                    return; // No notification to display
                }

                const notificationOptions: SbxNotificationOptions = {
                    body: notificationPayload.body,
                    icon: "/images/logo.php?logo=skin_logo_square&size=apple72",
                    image: notificationPayload.icon ?? "/images/logo.php?logo=skin_logo_square&size=apple72",
                    badge: "/images/logo.php?logo=skin_logo_square&size=apple72",
                    renotify: false,
                };

                if (notificationPayload.timestamp !== 0) {
                    notificationOptions.timestamp = notificationPayload.timestamp;
                }

                // TODO: Fall back to site name instead of "Notification" if no title is provided
                const notificationTitle: string = notificationPayload.title ?? "Notification";

                const notif = new Notification(notificationTitle, notificationOptions);
                notif.onclick = (event): void => {
                    event.preventDefault(); // prevent the browser from focusing the Notification's tab
                    // some notifications have a href, some don't just load the dashboard for those missing hrefs.
                    const notifHref: string = notificationPayload.href ?? "/";

                    // open the notification destination
                    window.open(notifHref, "_blank");
                };
            });
        }
    }
});

/**
 * Respond to a request to allow notifications, send both to firebase, and schoolbox
 * @param deviceId - An identifier for this device to allow de-duplication of registrations
 */
export async function requestNotif(deviceId: "iOS" | "Android" | "Browser"): Promise<void> {
    /**
     * Currently there'll usually only be at most one Firebase app, but if we start supporting
     * multiple apps, then we may need to start indexing the pwaConfig by app.appIdentifier too...
     */
    await Promise.all(
        window.sbProps.firebaseApps.map(async (app) => {
            if (!app.publicConfiguration || !app.webPushPublicKey || !app.appIdentifier) {
                return; // Skip this app if it doesn't have the required configuration.
            }

            /**
             * When the same device is re-registered, Firebase will invalidate all old devices automatically.
             * However, this doesn't remove the entry from Schoolbox's mobile app registrations.
             *
             * Ideally we would remove all tokens associated with this device, however, user A is not allowed to
             * delete user B's token in our API, so instead we just check if the user we are now registering
             * previously had a token, and if so, remove it from Schoolbox. This should be sufficient to
             * clean up the majority of old registratons but will leave at least one behind per device.
             */
            if (window.sbProps.pwaConfig.user.notificationToken) {
                // It's possible Schoolbox already realised this token was invalid and removed it,
                // so this may throw a 400 "Unable to find app registration", but that's fine.
                await fetch("/api/register/Firebase", {
                    method: "DELETE",
                    body: JSON.stringify({
                        appId: app.appIdentifier,
                        token: window.sbProps.pwaConfig.user.notificationToken,
                    }),
                    headers: {
                        "Content-Type": "application/json",
                    },
                });

                window.sbProps.pwaConfig.user.notificationToken = null;
            }

            // Set the current user's notification state to "registering" so we can retry if the page is reloaded before the registration is complete.
            window.sbProps.pwaConfig.user.notificationState = "registering";

            // Then, register the user's device with Firebase and Schoolbox.
            const firebaseApp = initializeApp(app.publicConfiguration);
            const messaging = getMessaging(firebaseApp);
            // Generate a new token, and store it in Schoolbox's
            // mobile app registration.
            getToken(messaging, { vapidKey: app.webPushPublicKey })
                .then(async (currentToken) => {
                    if (window.sbProps.pwaConfig.user.notificationState === "removing") {
                        // If notification blocked while requesting, should stop registering attempt
                        window.sbProps.pwaConfig.user.notificationState = null;
                        throw new Error("Notification blocked. Stop registering attempts.");
                    }

                    window.sbProps.pwaConfig.user.notificationToken = currentToken;

                    // The Firebase app may one day be a separate
                    // service to the other mobile apps: should that
                    // happen, the registration will be pushed to some
                    // other place.
                    // For now, just push it to the Firebase app.
                    const formData = new FormData();
                    formData.append("appId", app.appIdentifier);
                    formData.append("token", currentToken);
                    formData.append("deviceId", deviceId);

                    // Post this token to Schoolbox,
                    // registering it for the current user.
                    await fetch("/api/register/Firebase", {
                        method: "POST",
                        body: formData,
                    });

                    window.sbProps.pwaConfig.user.notificationState = "registered";
                })
                .catch((error) => {
                    console.error(`Error on getting token: ${error}`);
                });
        }),
    );
}

/**
 * Respond to a request to allow notifications, send both to firebase, and schoolbox
 */
export function denyNotif(): void {
    window.sbProps.firebaseApps.forEach((app) => {
        if (app.publicConfiguration && app.webPushPublicKey && app.appIdentifier) {
            const firebaseApp = initializeApp(app.publicConfiguration);
            const messaging = getMessaging(firebaseApp);
            // Generate a new token, and store it in Schoolbox's
            // mobile app registration.
            getToken(messaging, { vapidKey: app.webPushPublicKey })
                .then(async (currentToken) => {
                    void deleteToken(messaging);

                    // delete the local cache token for the user
                    window.sbProps.pwaConfig.user.notificationToken = null;

                    // Set notificationState to "removing" so if the page is reloaded we can re-trigger the event
                    window.sbProps.pwaConfig.user.notificationState = "removing";

                    // The Firebase app may one day be a separate
                    // service to the other mobile apps: should that
                    // happen, the registration will be pushed to some
                    // other place.
                    // For now, just push it to the Firebase app

                    // Post this token to Schoolbox,
                    // de-registering it for the current user.
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

                    window.sbProps.pwaConfig.user.notificationState = null;
                })
                .catch((error) => {
                    console.error(`Error on getting token: ${error}`);
                    window.sbProps.pwaConfig.user.notificationState = null; // Avoid repetatively requesting notifications from the user
                });
        }
    });
}
