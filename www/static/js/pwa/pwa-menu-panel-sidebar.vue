<script setup lang="ts">
/**
 * When the user clicks "Later" on the sidebar prompt, store it in the pwaConfig
 */
function onDismissPrompt(): void {
    window.sbProps.pwaConfig.user.hasClickedInstallLater = true;
}
</script>

<template>
    <li data-pwa="sidebarPrompt" class="content pwa-side-panel">
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
