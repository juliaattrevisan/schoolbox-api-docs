<script setup lang="ts">
/**
 * A modal to display content within, the start of a replacement
 * for foundation based <reveal-modal /> component
 */

window.Vue.onMounted(() => {
    console.warn("eh");
});
</script>

<template>
    <div class="modal">
        <div class="modal__bkg"></div>
    </div>
</template>

<style lang="scss" scoped>
.modal {
    z-index: var(--sbx-depth-modal);
    &__bkg {
        background-color: rgba(0, 0, 0, 0.5);
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
}
</style>
