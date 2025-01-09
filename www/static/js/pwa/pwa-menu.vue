<script setup lang="ts">
import PwaMenuInstall from "./pwa-menu-install.vue";
import PwaMenuNotifications from "./pwa-menu-notifications.vue";

interface Props {
    // if in chrome (messages sidebar) or in the notifications setting page
    page: "chrome" | "settings";
    // site title, to show "[title] web app" in the message
    siteTitle: string;
}

const props = defineProps<Props>();
</script>
<template>
    <div class="l-flex-col l-gap-m f-no-margins" :data-pwa-page="props.page">
        <p>
            To receive push notifications and enjoy a better experience, install the {{ props.siteTitle }} web app on
            your device.
        </p>
        <pwa-menu-install :page="props.page" :site-title="props.siteTitle" />
        <pwa-menu-notifications :page="props.page" :site-title="props.siteTitle" />
    </div>
</template>
<style scoped></style>
