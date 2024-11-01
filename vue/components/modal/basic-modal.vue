<script setup lang="ts">
/**
 * A modal to display content within, the start of a replacement
 * for foundation based <reveal-modal /> component
 */

const isOpen = defineModel<boolean>("is-open", { default: false }); // to open/close the modal

/**
 * To close the modal
 */
function onCloseClick(): void {
    isOpen.value = false;
}
</script>

<template>
    <teleport to="body">
        <div v-if="isOpen" class="modal">
            <div class="modal__container">
                <!-- @slot Reveal modal content goes into this slot -->
                <slot></slot>
                <button class="close-modal" title="Close modal" aria-label="Close modal" @click.prevent="onCloseClick">
                    <sbx-icon name="close" size="1.5rem" color="white" />
                </button>
            </div>
        </div>
    </teleport>
</template>

<style lang="scss" scoped>
.modal {
    background-color: rgba(0, 0, 0, 0.5);
    height: 100%;
    left: 0;
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
</style>
