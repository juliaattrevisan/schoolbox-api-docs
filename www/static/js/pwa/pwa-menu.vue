<script setup lang="ts">
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

// Each sub-menu component so we can see if they have any visible content
const pwaMenuInstallRef = window.Vue.useTemplateRef<InstanceType<typeof PwaMenuInstall>>("pwaMenuInstallRef");
const pwaMenuNotifRef = window.Vue.useTemplateRef<InstanceType<typeof PwaMenuNotifications>>("pwaMenuNotifRef");

/**
 * Whether at least one of the buttons is visible so we can hide the entire panel if not
 * Note: When hiding the panel, we must keep the components mounted so they can still reflect the PWA state
 */
const hasVisibleContent = window.Vue.computed(() => {
    // Each component must expose a 'hasVisibleContent' computed property
    const pwaMenuVisible = pwaMenuInstallRef.value?.hasVisibleContent || false;
    const notifMenuVisible = pwaMenuNotifRef.value?.hasVisibleContent || false;

    return pwaMenuVisible || notifMenuVisible;
});
</script>

<template>
    <component
        :is="props.page === 'settings' ? PwaMenuPanelSettings : PwaMenuPanelSidebar"
        :site-title="props.siteTitle"
        :has-visible-content="hasVisibleContent"
    >
        <div class="l-flex-col l-gap-m f-no-margins">
            <pwa-menu-install
                ref="pwaMenuInstallRef"
                :page="props.page"
                :site-title="props.siteTitle"
                :install-apple="props.installApple"
                :install-google="props.installGoogle"
                :pwa-enabled="props.pwaEnabled"
            />

            <pwa-menu-notifications
                v-if="notificationsEnabled"
                ref="pwaMenuNotifRef"
                :page="props.page"
                :site-title="props.siteTitle"
                :pwa-enabled="props.pwaEnabled"
            />
        </div>
    </component>
</template>

<style scoped></style>
