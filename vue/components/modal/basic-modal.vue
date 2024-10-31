<script setup lang="ts">
/**
 * A modal to display content within, the start of a replacement
 * for foundation based <reveal-modal /> component
 */

const isOpen = defineModel<boolean>("is-open", { default: false }); // to open/close the modal
</script>

<template>
    <Teleport to="body">
        <div v-if="isOpen" class="modal">
            <div class="modal__container">
                <!-- @slot Reveal modal content goes into this slot -->
                <slot></slot>
            </div>
            <div class="modal__bkg"></div>
        </div>
    </Teleport>
</template>

<style lang="scss" scoped>
.modal {
    height: 100%;
    left: 0;
    position: fixed;
    top: 0;
    width: 100%;
    z-index: var(--sbx-depth-modal);
    &__container {
        background: white;
        margin: 0 auto;
        padding: var(--spacing-m);
        position: absolute;
        top: 100px;
        width: 70%;
        z-index: calc(var(--sbx-depth-modal) + 1);
    }
    &__bkg {
        background-color: rgba(0, 0, 0, 0.5);
        height: 100%;
        left: 0;
        position: fixed;
        top: 0;
        width: 100%;
        z-index: calc(var(--sbx-depth-modal) - 1);
    }
}
</style>
