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
    <div class="l-flex-col l-gap-m f-no-margins" :data-pwa-page="props.page">
        <p>
            To receive push notifications and enjoy a better experience, install the {{ props.siteTitle }} web app on
            your device.
        </p>
    </div>
</template>
<style scoped></style>
