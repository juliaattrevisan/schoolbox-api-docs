<script setup lang="ts">
/**
 * A modal to display content within, the start of a replacement
 * for foundation based <reveal-modal /> component
 */

import { useTemplateRef } from "vue";
import { onKeyStroke } from "@vueuse/core";
import { useFocusTrap } from "@vueuse/integrations/useFocusTrap";

const modalEl = useTemplateRef<HTMLDivElement | null>("modalEl"); // to get the modal element
const isOpen = defineModel<boolean>("is-open", { default: false }); // to open/close the modal
const { activate, deactivate } = useFocusTrap(modalEl); // to keep focus within the modal when open

/**
 * Keep focus within the modal when it's open
 */
window.Vue.watch(isOpen, async (newIsOpen) => {
    if (newIsOpen) {
        await window.Vue.nextTick(); // to ensure the modal is rendered before activating the focus trap
        activate();
    } else {
        deactivate();
    }
});

/**
 * To close the modal
 */
function close(): void {
    if (!isOpen.value) return;
    isOpen.value = false;
}

/**
 * To handle the close click event
 */
function onCloseClick(): void {
    close();
}

/**
 *  To close the modal on the Escape key press
 */
onKeyStroke("Escape", () => {
    close();
});
</script>

<template>
    <teleport to="body">
        <transition>
            <div v-if="isOpen" ref="modalEl" class="modal">
                <div class="modal__container">
                    <!-- @slot Reveal modal content goes into this slot -->
                    <slot></slot>
                    <button
                        class="close-modal"
                        title="Close modal"
                        aria-label="Close modal"
                        @click.prevent="onCloseClick"
                    >
                        <sbx-icon name="close" size="1.5rem" color="white" />
                    </button>
                </div>
            </div>
        </transition>
    </teleport>
</template>

<style lang="scss" scoped>
.modal {
    background-color: rgba(0, 0, 0, 0.5);
    height: 100%;
    left: 0;
    overflow: auto;
    position: fixed;
    top: 0;
    width: 100%;
    z-index: var(--sbx-depth-modal);
    &__container {
        background: white;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.4);
        left: 15%;
        padding: var(--spacing-m);
        position: absolute;
        top: 100px;
        width: 70%;
    }
}

.close-modal {
    background: none;
    position: absolute;
    right: -2rem;
    top: -3rem;
}

// Fade in/out animation, linked to <transition> in the template
.v-enter-active,
.v-leave-active {
    transition: opacity 0.3s ease;
}

.v-enter-from,
.v-leave-to {
    opacity: 0;
}
</style>
