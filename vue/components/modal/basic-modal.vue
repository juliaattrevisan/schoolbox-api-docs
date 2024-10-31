<script setup lang="ts">
/**
 * A modal to display content within, the start of a replacement
 * for foundation based <reveal-modal /> component
 */

interface Props {
    isOpen: boolean; // whether the modal is open
}
const props = defineProps<Props>();
</script>

<template>
    <Teleport to="body">
        <div v-if="props.isOpen" class="modal">
            <div class="modal__bkg"></div>
        </div>
    </Teleport>
</template>

<style lang="scss" scoped>
.modal {
    height: 100%;
    left: 0;
    position: absolute;
    top: 0;
    width: 100%;
    z-index: var(--sbx-depth-modal);
    &__bkg {
        background-color: rgba(0, 0, 0, 0.5);
        height: 100%;
        left: 0;
        position: fixed;
        top: 0;
        width: 100%;
    }
}
</style>
