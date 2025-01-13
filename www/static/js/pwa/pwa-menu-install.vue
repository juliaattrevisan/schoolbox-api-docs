<script setup lang="ts">
import { getUserPlatform, isPWA } from "@js/util/browserUtils";
import PwaMenuShareIcon from "./pwa-menu-share-icon.vue";

interface Props {
    // if this component is located in the sidebar or the notifications setting page
    page: "sidebar" | "settings";
    // site title, to show "[title] web app" in the message
    siteTitle: string;
    // the digistorm prompt for google/apple, if it exists
    installApple: string | null;
    installGoogle: string | null;
    // whether the PWA app can be installed
    pwaEnabled: boolean;
}

const props = defineProps<Props>();

// The type of installation prompt to show
const pwaPromptType = window.Vue.computed(() => {
    // - If on iOS and the digistorm app exists
    if (props.installApple && getUserPlatform() === "iOS") {
        return { id: "iOS", url: props.installApple };
    }

    // - If on Android and the digistorm app exists
    if (props.installGoogle && getUserPlatform() === "Android") {
        return { id: "Android", url: props.installGoogle };
    }

    // - If PWA is enabled, we are outside of it, and the above are not true
    if (props.pwaEnabled && !isPWA()) {
        return { id: "Browser", url: null };
    }

    return { id: null, url: null };
});

/**
 * To tell the parent whether we are showing any content or not
 */
const hasVisibleContent = window.Vue.computed(() => {
    return pwaPromptType.value.id !== null;
});

defineExpose({ hasVisibleContent });
</script>
<template>
    <p v-if="pwaPromptType.id !== null">
        To receive push notifications and enjoy a better experience, install the {{ props.siteTitle }} web app on your
        device.
    </p>

    <a v-if="pwaPromptType.url" class="button" :href="pwaPromptType.url">Install</a>
    <a v-else-if="pwaPromptType.id !== null" data-pwa-action="installPWA" class="button" href="#">Install</a>

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
