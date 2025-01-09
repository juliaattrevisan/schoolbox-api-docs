<script setup lang="ts">
import PwaMenuInstall from "./pwa-menu-install.vue";
import PwaMenuNotifications from "./pwa-menu-notifications.vue";

interface Props {
    // if in chrome (messages sidebar) or in the notifications setting page
    page: "chrome" | "settings";
    // site title, to show "[title] web app" in the message
    siteTitle: string;
    // the digistorm prompt for google/apple, if it exists
    installApple: string | null;
    installGoogle: string | null;
}

const props = defineProps<Props>();
</script>
<template>
    <div class="l-flex-col l-gap-m f-no-margins" :data-pwa-page="props.page">
        <p>
            To receive push notifications and enjoy a better experience, install the {{ props.siteTitle }} web app on
            your device.
        </p>
        <a v-if="installApple" class="button" :href="installApple">Install</a>
        <a v-if="installGoogle" class="button" :href="installGoogle">Install</a>
    </div>
</template>
<style scoped></style>
