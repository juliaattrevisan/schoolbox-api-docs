/**
 * The logic to handle the PWA config for the current user.
 * It supports handling switching users and keeping the config in sync with that user's last settings.
 *
 * This store acts as the single source of truth for the PWA config and will automatically save changes
 * to localStorage when the config is modified.
 */
import { defineStore } from "pinia";

const localStorageKey = "schoolboxPWAConfig"; // The key to use in localStorage to store the PWA config

interface PWAConfig {
    users: Record<
        number, // window.schoolboxUser.id
        {
            // Whether the user has clicked the "Later" button on the PWA install prompt and we should not show the install prompt again
            hasClickedInstallLater: boolean;
            // Track what state we are in for registering notifications for the user in case the page is reloaded and we need to re-register
            // - null: No registration has been attempted
            // - "registering": Currently in the process of registering, or page was reloaded mid-registration and should retry
            // - "registered": The user has granted permission and the device is registered in firebase
            // - "removing": Currently in the process of removing, or page was reloaded mid-removal and should retry
            notificationState: null | "registering" | "registered" | "removing";
            // The Firebase Cloud Messaging registration token that can be used to send push messages to that Messaging instance.
            notificationToken: null | string;
        }
    >;
    version: 1; // To track the version of the config and allow for migration/invalidation of old cache when we inevitably change the structure
}

/**
 * The default PWA config that will be used if there is no existing config in local storage
 * The default user config is also specified and will be used if the current user does not already have a config
 * NOTE: When changing the config, ensure that the version is incremented correctly to invalidate the cache of old users (as per the type)
 */
const defaultPWAUserConfig: PWAConfig["users"][number] = {
    hasClickedInstallLater: false,
    notificationState: null,
    notificationToken: null,
};

const defaultPWAConfig: PWAConfig = {
    users: {},
    version: 1,
};

export const usePwaConfigStore = defineStore(`pwaConfig`, () => {
    const currentUserId = window.schoolboxUser?.id ?? -1; // The current user's ID, loaded from chrome.twig, or -1 if not available (logged out)

    const existingPWAConfig = localStorage.getItem(localStorageKey);
    let localPWAConfig = existingPWAConfig !== null ? (JSON.parse(existingPWAConfig) as PWAConfig) : defaultPWAConfig;

    // If the version has changed, invalidate the cache and reset the config
    // You could also choose to make a migration here based on two version numbers if you don't want to reset the config and break existing user's notifications
    if (localPWAConfig.version === undefined || localPWAConfig.version !== defaultPWAConfig.version) {
        localStorage.removeItem(localStorageKey);
        localPWAConfig = defaultPWAConfig;
    }

    // If the current user does not have a config, create one for them
    if (Object.prototype.hasOwnProperty.call(localPWAConfig.users, currentUserId) === false) {
        localPWAConfig.users[currentUserId] = defaultPWAUserConfig;
    }

    // Create a reactive object to later allow watching for changes to the user config
    // (It is safe to use Vue here as we are certain it is loaded on all pages before this script is run)
    const reactivePWAConfig = window.Vue.ref({
        config: localPWAConfig,
        user: localPWAConfig.users[currentUserId], // Intentionally reference the object (not clone) so modifictations are directly linked back to localPWAConfig
    });

    /**
     * Watch for changes to the user config and automatically save them to localStorage
     * Using the watch here ensures the state of the config is always saved and updated everywhere,
     * and any part of the app can make changes without worrying about save logic/syncing/etc.
     */
    window.Vue.watch(
        reactivePWAConfig,
        (newConfig) => {
            localStorage.setItem(localStorageKey, JSON.stringify(newConfig.config));
        },
        { deep: true },
    );

    return {
        config: reactivePWAConfig.value.config,
        user: reactivePWAConfig.value.user,
    };
});
