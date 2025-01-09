<script setup lang="ts">
import { getUserPlatform, isPWA, supportsNotifications } from "@js/util/browserUtils";

interface Props {
    // if in chrome (messages sidebar) or in the notifications setting page
    page: "chrome" | "settings";
    // site title, to show "[title] web app" in the message
    siteTitle: string;
    // whether the PWA app can be installed
    pwaEnabled: boolean;
}

const props = defineProps<Props>();

enum PromptType {
    IMPERSONATING_USER,
    IOS_HOME_PROMPT,
    NOTIF_BLOCKED,
    NOTIF_ENABLED_MESSAGE,
    ENABLE_NOTIF_PROMPT,
}

// ---------------------- TODO: This stuff should be moved elsewhere, but it's here for now

// The browser's current notification permission status
const currentPermission = window.Vue.ref<"default" | "granted" | "denied">("default");

/**
 * As Notification.permission cannot be trusted on iOS, we can use the PWA config to determine
 * if we should avoid showing "Enable Notifications" again when the user has already enabled them
 *
 * If the browser is saying notifications are blocked, respect that, even though it may be wrong
 * as we handle this separately by providing a button to re-request permission instead.
 */
if (
    getUserPlatform() === "iOS" &&
    currentPermission.value === "default" &&
    ![null, "removing"].includes(window.sbProps.pwaConfig.user.notificationState)
) {
    // Treat "default" as "granted" on iOS if the user has allowed notifications in the past
    currentPermission.value = "granted";
}

// To keep track of the initial permission state when the page was loaded so we can show the correct prompt
const initialPermission = window.Vue.ref<"default" | "granted" | "denied">(currentPermission.value);
// ------------------

/**
 * The prompt to show, if any
 */
const prompt = window.Vue.computed<PromptType | null>(() => {
    // If the user is impersonating another user, notification functionality is disabled
    if (window.schoolboxUser.impersonated) {
        // only show the impersonation message on the settings page
        if (props.page === "settings") {
            return PromptType.IMPERSONATING_USER;
        }
        // otherwise, don't show any prompts
        return null;
    }

    /**
     * Regardless of if the browser supports notifications or not,
     * If the user is on iOS and outside of the PWA, show the iOS home prompt.
     * iOS requires the user to be in the PWA to before enabling notifications.
     * Only show if PWA is enabled in admin.
     */
    if (getUserPlatform() === "iOS" && !isPWA()) {
        // Only show the iOS home prompt if PWA is enabled
        return props.pwaEnabled ? PromptType.IOS_HOME_PROMPT : null;
    }

    // If the browser doesn't support notifications, nothing to show from now on
    if (!supportsNotifications()) {
        return null;
    }

    // If the user has allowed notifications
    if (currentPermission.value === "granted") {
        // show the enabled message if:
        // - this is the settings page
        // - or if the user has just enabled notifications this page session
        if (props.page === "settings" || initialPermission.value !== "granted") {
            return PromptType.NOTIF_ENABLED_MESSAGE;
        }
        return null;
    }

    // If the user has blocked notifications, show the blocked message
    if (currentPermission.value === "denied") {
        return PromptType.NOTIF_BLOCKED;
    }

    // If the user hasn't been prompted yet, show the enable notifications prompt
    return PromptType.ENABLE_NOTIF_PROMPT;
});
</script>
<template>
    <div v-if="prompt === PromptType.IMPERSONATING_USER" class="alert-box warning">
        Device notifications are disabled while impersonating another user.
    </div>

    <div v-else-if="prompt === PromptType.IOS_HOME_PROMPT" class="alert-box warning">
        To enable notifications on your iOS device, you must install the {{ props.siteTitle }} web app on your device
        and add it to your device's home screen.
    </div>

    <div v-else-if="prompt === PromptType.NOTIF_BLOCKED" class="alert-box warning">
        You've blocked notifications on this device. To enable notifications, you must go to your device settings and
        set <b>Allow notifications</b>.
        <a href="#" class="pwa-retry-notif-prompt">Try again</a>
    </div>

    <div v-else-if="prompt === PromptType.NOTIF_ENABLED_MESSAGE" class="alert-box success">
        You have notifications enabled for this device
    </div>

    <div v-else-if="prompt === PromptType.ENABLE_NOTIF_PROMPT" class="l-flex-col l-gap-s f-no-margins">
        <p>Enable notifications on your device:</p>
        <a data-pwa-action="enableNotif" class="button" href="#">Enable Notifications</a>
    </div>

    <p v-if="props.page === 'settings'" class="notifNote">
        <strong>Note:</strong> If you want to receive push notifications on multiple devices, you must enable
        notifications on all your devices.
    </p>
</template>
<style scoped></style>
