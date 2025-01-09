<script setup lang="ts">
import { getUserPlatform, isPWA } from "@js/util/browserUtils";
import PwaMenuPanelSettings from "./pwa-menu-panel-settings.vue";
import PwaMenuPanelSidebar from "./pwa-menu-panel-sidebar.vue";
import PwaMenuInstall from "./pwa-menu-install.vue";
import PwaMenuNotifications from "./pwa-menu-notifications.vue";

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
    // whether notifications are enabled
    notificationsEnabled: boolean;
}

const props = defineProps<Props>();

// The type of installation prompt to show
// - iOS : If on iOS and the digistorm app exists
// - Android : If on Android and the digistorm app exists
// - PWA : If PWA is enabled, we are outside of it, and the above are not true
const pwaPromptType = window.Vue.computed(() => {
    if (props.installApple && getUserPlatform() === "iOS") {
        return { id: "iOS", url: props.installApple };
    }

    if (props.installGoogle && getUserPlatform() === "Android") {
        return { id: "Android", url: props.installGoogle };
    }

    if (props.pwaEnabled && !isPWA()) {
        return { id: "Browser", url: null };
    }

    return { id: null, url: null };
});
</script>

<template>
    <component
        :is="props.page === 'settings' ? PwaMenuPanelSettings : PwaMenuPanelSidebar"
        :site-title="props.siteTitle"
    >
        <div class="l-flex-col l-gap-m f-no-margins">
            <p>
                To receive push notifications and enjoy a better experience, install the {{ props.siteTitle }} web app
                on your device.
            </p>

            <a v-if="pwaPromptType.url" class="button" :href="pwaPromptType.url">Install</a>
            <pwa-menu-install
                v-else-if="pwaPromptType.id === 'Browser'"
                :page="props.page"
                :site-title="props.siteTitle"
            />

            <pwa-menu-notifications
                v-if="notificationsEnabled"
                :page="props.page"
                :site-title="props.siteTitle"
                :pwa-enabled="props.pwaEnabled"
            />
        </div>
    </component>
</template>

<style scoped></style>
