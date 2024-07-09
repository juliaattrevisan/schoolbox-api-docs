/**
 * The logic to handle the PWA config for the current user.
 * It supports handling switching users and keeping the config in sync with that user's last settings.
 *
 * This is added to window.sbProps.pwaConfig and should only be accessed through that object
 * as to ensure different parts of the page are always modifying the same object and don't fall out of sync.
 *
 * This script acts as the single source of truth for the PWA config and will automatically save changes
 * to localStorage when the config is modified.
 *
 * If we come up with more useful cases for a managed localStorage item, we could further generalise ths logic
 * to act as a schoolbox localStorage manager. For now, it is just for the PWA config.
 *
 * You should not import this file directly, instead access the pwaConfig from window.sbProps.pwaConfig
 */

const localStorageKey = "schoolboxPWAConfig"; // The key to use in localStorage to store the PWA config
const currentUserId = window.schoolboxUser.id; // The current user's ID, loaded from chrome.twig

interface PWAConfig {
    users: Record<
        typeof currentUserId,
        {
            // Whether the user has clicked the "Later" button on the PWA install prompt and we should not show the install prompt again
            hasClickedInstallLater: boolean;
            // Track what state we are in for registering notifications for the user in case the page is reloaded and we need to re-register
            notificationState: null | "registering" | "registered" | "removing" | "removed" | "denied";
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
const defaultPWAUserConfig: PWAConfig["users"][typeof currentUserId] = {
    hasClickedInstallLater: false,
    notificationState: null,
    notificationToken: null,
};
const defaultPWAConfig: PWAConfig = {
    users: {},
    version: 1,
};

const existingPWAConfig = localStorage.getItem(localStorageKey);
let pwaConfig = existingPWAConfig !== null ? (JSON.parse(existingPWAConfig) as PWAConfig) : defaultPWAConfig;

// If the version has changed, invalidate the cache and reset the config
// You could also choose to make a migration here based on two version numbers if you don't want to reset the config and break existing user's notifications
if (pwaConfig.version === undefined || pwaConfig.version !== defaultPWAConfig.version) {
    localStorage.removeItem(localStorageKey);
    pwaConfig = defaultPWAConfig;
}

// If the current user does not have a config, create one for them
if (Object.prototype.hasOwnProperty.call(pwaConfig.users, currentUserId) === false) {
    pwaConfig.users[currentUserId] = defaultPWAUserConfig;
}

// Create a reactive object to later allow watching for changes to the user config
// (It is safe to use Vue here as we are certain it is loaded on all pages before this script is run)
const reactivePWAConfig = window.Vue.ref({
    config: pwaConfig,
    user: pwaConfig.users[currentUserId], // Intentionally reference the object (not clone) so modifictations are directly linked back to pwaConfig
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

export const pwaUserConfig = reactivePWAConfig.value.user;
