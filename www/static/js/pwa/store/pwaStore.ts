/**
 * Handles the state of the PWA and notifications across all pwa components on the page
 */

import { PWA_NOTIFICATION_ALLOW, PWA_NOTIFICATION_DENY } from "@js/firebase/firebaseEvent";
import type { FirebaseDenyEvent, FirebaseRequestNotifEvent } from "@js/firebase/firebaseEvent";
import { getUserPlatform, supportsNotifications } from "@js/util/browserUtils";
import type { BeforeInstallPromptEvent } from "@js/util/browserUtils";
import { defineStore } from "pinia";

export const usePwaStore = defineStore(`pwa`, () => {
    // #region PWA APP

    // The deferred PWA prompt event that can be used to trigger the install prompt
    // Depending on the browser, this may never be set to a value
    const deferredPrompt = window.Vue.ref<BeforeInstallPromptEvent | null>(null);

    // If the browser supports the beforeinstallprompt event, store it
    // https://web.dev/articles/codelab-make-installable
    window.addEventListener("beforeinstallprompt", (event) => {
        // Prevent the mini-infobar from appearing on mobile
        event.preventDefault();

        // Stash the event so it can be triggered by the user later
        deferredPrompt.value = event as BeforeInstallPromptEvent;
    });

    // When the app is installed, clear the deferred prompt
    window.addEventListener("appinstalled", () => {
        deferredPrompt.value = null;
    });

    // #endregion

    // #region NOTIFICATIONS

    // The browser's current notification permission status
    const currentNotifPermission = window.Vue.ref<NotificationPermission | null>(
        supportsNotifications() ? Notification.permission : null,
    );

    /**
     * As Notification.permission cannot be trusted on iOS, we can use the PWA config to determine
     * if we should avoid showing "Enable Notifications" again when the user has already enabled them
     *
     * If the browser is saying notifications are blocked, respect that, even though it may be wrong
     * as we handle this separately by providing a button to re-request permission instead.
     */
    if (
        getUserPlatform() === "iOS" &&
        currentNotifPermission.value === "default" &&
        ![null, "removing"].includes(window.sbProps.pwaConfig.user.notificationState)
    ) {
        // Treat "default" as "granted" on iOS if the user has allowed notifications in the past
        currentNotifPermission.value = "granted";
    }

    // To keep track of the initial permission state when the page was loaded so we can show the correct prompt
    const initialNotifPermission = currentNotifPermission.value;

    /**
     * When the same device is re-registered, Firebase will invalidate all old devices automatically.
     * Check if any users that aren't the current logged in user have (according to pwaConfig) previously been registered
     * and if so, invalidate their registration locally so we can re-register them when they next log in.
     *
     * NOTE: We should NOT send PWA_NOTIFICATION_DENY in this case, as it will de-register the current user too!
     *
     * We keep the old notificationToken in the config so if the user logs in again, we can clean up the old token in
     * the schoolbox mobile app registration database. See firebase-init for more details about this scenario.
     */
    Object.entries(window.sbProps.pwaConfig.config.users).forEach(([userId, pwaUserConfig]) => {
        if (userId.toString() !== window.schoolboxUser.id.toString()) {
            pwaUserConfig.notificationState = null;
        }
    });

    if (initialNotifPermission === "granted") {
        /**
         * If this device is now allowing notifications but we haven't registered it yet,
         * then attempt to register the device now...
         */
        if (window.sbProps.pwaConfig.user.notificationState !== "registered") {
            window.sbProps.pwaConfig.user.notificationState = "registering";
        }
    } else if (initialNotifPermission === "denied") {
        /**
         * As the user has now blocked notifications,
         * set the notification state to removing so firebase can clean up on page load
         */
        if (window.sbProps.pwaConfig.user.notificationState !== null) {
            window.sbProps.pwaConfig.user.notificationState = "removing";
        }
    }

    // Only prompt the user for notifications once per page load
    const hasPromptedForNotif = window.Vue.ref(false);

    /**
     * Handle the user's decision to allow or deny notifications
     * or when the page loads and the device was mid-registration/deregistration
     */
    function handleNotifPermissionChange(): void {
        /**
         * If the user didn't grant permission, we can't do anything else here.
         *
         * This may be "denied" or "default", but in the case of "default" - we should treat as "denied" as per
         * https://developer.mozilla.org/en-US/docs/Web/API/Notification/requestPermission_static#return_value
         */
        if (
            currentNotifPermission.value !== "granted" ||
            window.sbProps.pwaConfig.user.notificationState === "removing" // or if we are in the process of removing already
        ) {
            // Tell firebase and schoolbox that the user denied notifications
            document.dispatchEvent(new CustomEvent<FirebaseDenyEvent["detail"]>(PWA_NOTIFICATION_DENY));
            return;
        }

        // If the user has granted permission, attempt to register the device
        window.sbProps.pwaConfig.user.notificationState = "registering";

        // Attempt to register the device for notifications
        document.dispatchEvent(
            new CustomEvent<FirebaseRequestNotifEvent["detail"]>(PWA_NOTIFICATION_ALLOW, {
                detail: {
                    deviceId: getUserPlatform(),
                },
            }),
        );

        /**
         * Show a sample notification, if we have the ability to do so
         * iOS requires notifications to be dispatched from inside a service worker, so send it there
         */
        if ("serviceWorker" in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: "SHOW_NOTIFICATION",
                title: window.sbProps.siteTitle,
                options: {
                    body: `Welcome! Your notifications are powered by the ${window.sbProps.siteTitle} web app`,
                    icon: "/images/logo.php?logo=skin_logo_square&size=apple72",
                    image: "/images/logo.php?logo=skin_logo_square&size=apple72",
                    badge: "/images/logo.php?logo=skin_logo_square&size=apple72",
                    renotify: false,
                    data: {
                        href: "/settings/notifications",
                    },
                },
            });
        }
    }

    /**
     * Handle the user clicking the "Enable Notifications" button
     */
    async function onEnableNotifications(): Promise<void> {
        if (hasPromptedForNotif.value) {
            return;
        }
        hasPromptedForNotif.value = true;

        // Use await instead of .then to avoid iOS treating as a non-interaction and failing
        currentNotifPermission.value = await Notification.requestPermission();

        window.sbProps.pwaConfig.user.notificationState = null; // Reset the state since it was a user action
        handleNotifPermissionChange();
    }

    /**
     * Sometimes the service worker is not running when we prompt attempt to register notifications, try again if we failed..
     * This could also be if we were registering notifications and it was interrupted by a page reload or similar
     */
    function handleInitialNotificationState(): void {
        // If we were registering notifications, try again
        if (window.sbProps.pwaConfig.user.notificationState === "registering") {
            handleNotifPermissionChange();
        }

        // If we were removing notifications, try again
        if (window.sbProps.pwaConfig.user.notificationState === "removing") {
            handleNotifPermissionChange();
        }
    }

    if (document.readyState !== "loading") {
        handleInitialNotificationState();
    } else {
        document.addEventListener("DOMContentLoaded", handleInitialNotificationState);
    }

    // #endregion

    return {
        deferredPrompt,
        currentNotifPermission,
        initialNotifPermission,
        onEnableNotifications,
        hasPromptedForNotif,
    };
});
