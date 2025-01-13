<script setup lang="ts">
interface Props {
    // whether the panel has any visible content to show in it
    hasVisibleContent: boolean;
    // site title, to show "[title] web app" in the message
    siteTitle: string;
}

const props = defineProps<Props>();

/**
 * When the user clicks "Later" on the sidebar prompt, store it in the pwaConfig
 */
function onDismissPrompt(): void {
    window.sbProps.pwaConfig.user.hasClickedInstallLater = true;
}

/**
 * Show the panel if there is any content to show in it
 * and the user has not dismissed it before
 */
const showPanel = window.Vue.computed(() => {
    if (window.sbProps.pwaConfig.user.hasClickedInstallLater === true) {
        return false;
    }
    return props.hasVisibleContent;
});
</script>

<template>
    <li v-show="showPanel" data-pwa="sidebarPrompt" class="content pwa-side-panel">
        <slot></slot>
        <a
            data-pwa="sidebarPromptDelay"
            class="button load-more expand install-delay-prompt"
            href="#"
            @click.prevent="onDismissPrompt"
        >
            Later
        </a>
    </li>
</template>

<style scoped></style>
