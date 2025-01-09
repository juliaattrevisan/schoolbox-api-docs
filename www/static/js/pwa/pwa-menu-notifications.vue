<script setup lang="ts">
interface Props {
    // if in chrome (messages sidebar) or in the notifications setting page
    page: "chrome" | "settings";
    // site title, to show "[title] web app" in the message
    siteTitle: string;
}

const props = defineProps<Props>();
</script>
<template>
    <div data-pwa-notif="iOSHomePrompt" class="alert-box warning">
        To enable notifications on your iOS device, you must install the {{ props.siteTitle }} web app on your device
        and add it to your device's home screen.
    </div>

    <div data-pwa-notif="notifBlocked" class="alert-box warning">
        You've blocked notifications on this device. To enable notifications, you must go to your device settings and
        set <b>Allow notifications</b>.
        <a data-pwa-action="retryNotifPrompt" class="pwa-retry-notif-prompt" href="#">Try again</a>
    </div>

    <div data-pwa-notif="notifDisabledImpersonate" class="alert-box warning">
        Device notifications are disabled while impersonating another user.
    </div>

    <div data-pwa-notif="notifEnabledMessage" class="alert-box success">
        You have notifications enabled for this device
    </div>

    <div data-pwa-notif="enableNotifPrompt" class="l-flex-col l-gap-s f-no-margins">
        <p>Enable notifications on your device:</p>
        <a data-pwa-action="enableNotif" class="button" href="#">Enable Notifications</a>
    </div>

    <p v-if="props.page === 'settings'" class="notifNote">
        <strong>Note:</strong> If you want to receive push notifications on multiple devices, you must enable
        notifications on all your devices.
    </p>
</template>
<style scoped></style>
