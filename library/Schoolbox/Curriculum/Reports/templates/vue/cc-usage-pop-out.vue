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
    // referenceElement.value?.classList.remove("is-selected");
    popOutStore.button = null;
}

/**
 *
 */
const { onButtonSelected } = usePopOut(
    button,
    popOutElement,
    "",
    "right-start",
    undefined,
    undefined,
    false,
    undefined,
    onPopOutClosed,
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
        <p>Hai</p>
    </div>
</template>

<style lang="scss" scoped>
.pop-out {
    background-color: #fff;
    border-radius: 0.25rem;
    box-shadow: 0px 0px 6px rgba(0, 0, 0, 0.2);
    min-width: 16rem;
    min-height: 8rem;
    padding: 0.5rem 0;
    position: absolute;
    z-index: var(--sbx-depth-pop-over);

    &__divider {
        border: 1px solid rgba(255, 0, 0, 0.15);
        margin-block-end: 0;
        margin-block-start: 0.5rem;
    }
}
</style>
