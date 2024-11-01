<script setup lang="ts">
/**
 * A modal to display content within, the start of a replacement
 * for foundation based <reveal-modal /> component
 */

import { useTemplateRef } from "vue";
import { onKeyStroke } from "@vueuse/core";
import { useFocusTrap } from "@vueuse/integrations/useFocusTrap";

const modalContainerEl = useTemplateRef<HTMLDivElement | null>("searchSelectEl");

const isOpen = defineModel<boolean>("is-open", { default: false }); // to open/close the modal

const { activate, deactivate } = useFocusTrap(modalContainerEl);

/**
 * Keep focus within the modal when it's open
 */
window.Vue.watch(isOpen, (newIsOpen) => {
    if (newIsOpen) {
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
            <div v-if="isOpen" class="modal">
                <div ref="modalContainerEl" class="modal__container">
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
        z-index: calc(var(--sbx-depth-modal) + 1);
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
