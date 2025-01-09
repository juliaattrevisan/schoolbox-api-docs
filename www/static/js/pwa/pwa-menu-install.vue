<script setup lang="ts">
import PwaMenuShareIcon from "./pwa-menu-share-icon.vue";

interface Props {
    // if in chrome (messages sidebar) or in the notifications setting page
    page: "chrome" | "settings";
    // site title, to show "[title] web app" in the message
    siteTitle: string;
}

const props = defineProps<Props>();
</script>
<template>
    <a data-pwa-action="installApple" class="button hide" href="{{ installApple }}">Install</a>
    <a data-pwa-action="installGoogle" class="button hide" href="{{ installGoogle }}">Install</a>
    <a data-pwa-action="installPWA" class="button hide" href="#">Install</a>
    <a data-pwa-action="installPWAiOS" data-reveal-id="PWAiOSAddToHomescreenReveal-{{ pwaPage }}" class="button hide"
        >Install</a
    >

    <div data-reveal class="reveal-modal">
        <h1>Add the {{ props.siteTitle }} web app to your home screen</h1>
        <a class="close-reveal-modal" aria-label="Close">&#215;</a>
        <p>
            To receive push notifications and enjoy a better experience, install the {{ props.siteTitle }} web app on
            your Apple device.
        </p>

        <ol>
            <li>Select the <pwa-menu-share-icon /> <strong> Share </strong> button in the menu bar of your browser.</li>
            <li>From the list of options, select <strong>Add to Home Screen</strong>.</li>
        </ol>

        <p>
            If 'Add to Home Screen' option doesn't exist, you can scroll down the list and select
            <strong>Edit Actions</strong> and then <strong>Add to Home Screen</strong>.
        </p>

        <p>
            Once you've added the {{ props.siteTitle }} web app to your home screen, you must then go to the web app on
            your home screen to enable notifications.
        </p>
        <p>If you're still unable to add it to your home screen, please contact your support team.</p>
    </div>
</template>
<style scoped></style>
