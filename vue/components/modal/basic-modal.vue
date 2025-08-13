<script setup lang="ts">
/**
 * A modal to display content within, the start of a replacement
 * for foundation based <reveal-modal /> component
 */

import { useTemplateRef } from "vue";
import { onKeyStroke } from "@vueuse/core";
import { useFocusTrap } from "@vueuse/integrations/useFocusTrap";
import { vOnClickOutside } from "@vueuse/components";

interface Props {
    hasBackgroundClick?: boolean; // Whether to close the modal on background click (clicking outside the modal)
    size?: "m" | "s"; // Size of the modal, 'm' for medium and 's' for small
}
const props = defineProps<Props>();

const modalSize = window.Vue.computed(() => props.size || "m"); // Default size is medium
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

/**
 * To close the modal on background click (clicking outside the modal)
 */
function onBackgroundClick(): void {
    if (props.hasBackgroundClick) {
        close();
    }
}
</script>

<template>
    <teleport to="body">
        <transition>
            <div v-if="isOpen" ref="modalEl" class="modal">
                <div v-on-click-outside="onBackgroundClick" :class="`modal__container modal__container--${modalSize}`">
                    <!-- @slot Reveal modal content goes into this slot -->
                    <div class="modal__container_main"><slot></slot></div>
                    <slot name="footer"></slot>
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
        margin: 0 auto;
        position: relative;
        top: 100px;
    }
    /* set the width and breakpoint based on the existence of the size class */
    /* I feel there should be a better way to do this with v-bind() and loop, but for now this will do */
    &:has(.modal__container--m) {
        .modal__container--m {
            width: 1000px;
        }
        @media (max-width: 1100px) {
            .modal__container--m {
                width: 100%;
            }
            .close-modal {
                right: 0;
                top: -3rem;
            }
        }
    }
    &:has(.modal__container--s) {
        .modal__container--s {
            width: 500px;
        }
        @media (max-width: 600px) {
            .modal__container--s {
                width: 100%;
            }
            .close-modal {
                right: 0;
                top: -3rem;
            }
        }
    }
    /* separate so that the footer can have different padding (i.e. to use component actions) */
    &__container_main {
        padding: var(--spacing-m);
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
