<script setup lang="ts">
/**
 * Shows a particular nodes usage in a pop out window
 */

import { useTemplateRef } from "vue";
import { usePopOut } from "@schoolboxVue/composables/popOut/usePopOut";
import { usePopOutStore } from "./store/ccPopOutStore";

const popOutElement = useTemplateRef<HTMLDivElement>("popOutElement");
const popOutStore = usePopOutStore();
const button = window.Vue.ref<HTMLButtonElement | null>(null);

/**
 * When the pop out is closed, clear out the selected button to allow
 * for possible reselection of the same button
 */
function onPopOutClosed(): void {
    popOutStore.button = null;
}

/**
 * When the button is selected, open the pop out
 */
const { onButtonSelected } = usePopOut(
    button,
    popOutElement,
    "",
    "right-start",
    undefined,
    undefined,
    undefined,
    onPopOutClosed,
    false,
);

/**
 * Open the pop out modal, waits for a tick to ensure the element is available
 * since we just changed it.
 */
async function openPopOut(): Promise<void> {
    await window.Vue.nextTick();
    await onButtonSelected();
}

/**
 * Watch the button, triggering the pop out when it changes
 */
window.Vue.watch(
    () => popOutStore.button,
    (newButton) => {
        if (newButton === null) {
            return;
        }
        button.value = newButton;
        void openPopOut();
    },
);
</script>

<template>
    <div ref="popOutElement" class="pop-out" style="display: none">
        <div v-if="popOutStore.hasLoadError" class="alert-box alert f-no-margins">
            There was an error during data retrieval. Please
            <a href="" title="refresh the page"> refresh the page </a>
            to try again and contact your support if the problem persists
        </div>
        <div v-else-if="popOutStore.isLoading && !popOutStore.hasLoadError" class="pop-out-menu__loader">
            <div class="loader"></div>
        </div>
        <div v-else>
            <p v-if="popOutStore.isNodeExpanded">Open</p>
            <p v-else>Closed</p>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.pop-out {
    background-color: #fff;
    border-radius: 0.25rem;
    box-shadow: 0px 0px 6px rgba(0, 0, 0, 0.2);
    max-width: 16rem;
    min-height: 8rem;
    min-width: 16rem;
    padding: var(--spacing-s);
    position: absolute;
    z-index: var(--sbx-depth-pop-over);
}
</style>
