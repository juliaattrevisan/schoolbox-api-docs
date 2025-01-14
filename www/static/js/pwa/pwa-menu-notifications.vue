<script setup lang="ts">
import { sbProps } from "@js/util/sbx/pageUtils";
import { getUserPlatform, isPWA, supportsNotifications } from "@js/util/browserUtils";
import { usePwaStore } from "./store/pwaStore";

interface Props {
    // if this component is located in the sidebar or the notifications setting page
    page: "sidebar" | "settings";
    // whether the PWA app can be installed
    pwaEnabled: boolean;
}

const props = defineProps<Props>();
const pwaStore = usePwaStore();

enum PromptType {
    IMPERSONATING_USER,
    IOS_HOME_PROMPT,
    NOTIF_BLOCKED,
    NOTIF_ENABLED_MESSAGE,
    ENABLE_NOTIF_PROMPT,
}

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
    if (pwaStore.currentNotifPermission === "granted") {
        // show the enabled message if:
        // - this is the settings page
        // - or if the user has just enabled notifications this page session
        if (props.page === "settings" || pwaStore.initialNotifPermission !== "granted") {
            return PromptType.NOTIF_ENABLED_MESSAGE;
        }
        return null;
    }

    // If the user has blocked notifications, show the blocked message
    if (pwaStore.currentNotifPermission === "denied") {
        return PromptType.NOTIF_BLOCKED;
    }

    // If the user hasn't been prompted yet, show the enable notifications prompt
    return PromptType.ENABLE_NOTIF_PROMPT;
});

/**
 * on iOS, Notification.permission is completely unreliable until we have used
 * Notification.requestPermission at least once. If a user initially denies notifications,
 * but then turns them on in settings, iOS will still return 'denied' for Notification.permission
 * even if you navigate between pages or refresh the whole app sometimes...
 * We can't just auto re-request permission because Notification.requestPermission() requires a user gesture
 *
 * To work around this, we provide a button on the blocked message that the user can click to
 * trigger Notification.requestPermission() again and then reload the page to get the reflected UI changes
 */
async function onRetryNotifPrompt(): Promise<void> {
    await Notification.requestPermission();
    window.location.reload();
}

/**
 * To tell the parent whether we are showing any content or not
 */
const hasVisibleContent = window.Vue.computed(() => {
    return prompt.value !== null;
});

defineExpose({ hasVisibleContent });
</script>
<template>
    <div v-if="prompt === PromptType.IMPERSONATING_USER" class="alert-box warning">
        Device notifications are disabled while impersonating another user.
    </div>

    <div v-else-if="prompt === PromptType.IOS_HOME_PROMPT" class="alert-box warning">
        To enable notifications on your iOS device, you must install the {{ sbProps.siteTitle }} web app on your device
        and add it to your device's home screen.
    </div>

    <div v-else-if="prompt === PromptType.NOTIF_BLOCKED" class="alert-box warning">
        You've blocked notifications on this device. To enable notifications, you must go to your device settings and
        set <b>Allow notifications</b>.
        <a href="#" class="pwa-retry-notif-prompt" @click.prevent="onRetryNotifPrompt">Try again</a>
    </div>

    <div v-else-if="prompt === PromptType.NOTIF_ENABLED_MESSAGE" class="alert-box success">
        You have notifications enabled for this device
    </div>

    <div v-else-if="prompt === PromptType.ENABLE_NOTIF_PROMPT" class="l-flex-col l-gap-s f-no-margins">
        <p>Enable notifications on your device:</p>
        <a
            data-pwa-action="enableNotif"
            class="button"
            href="#"
            :disabled="pwaStore.hasPromptedForNotif || undefined"
            @click="pwaStore.onEnableNotifications"
        >
            Enable Notifications
        </a>
    </div>

    <p v-if="props.page === 'settings'" class="notifNote">
        <strong>Note:</strong> If you want to receive push notifications on multiple devices, you must enable
        notifications on all your devices.
    </p>
</template>
<style scoped></style>
