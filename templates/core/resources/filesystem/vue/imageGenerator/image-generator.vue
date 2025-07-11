<script lang="ts" setup>
/**
 * AI Image Generator used inside the resource picker modal
 */
import IgPrompt from "./ig-prompt.vue";
</script>

<template>
    <h2>Generate an Image</h2>
    <ig-prompt />
</template>

<style scoped lang="scss">
h2 {
    /* override _dropzone.scss [.reveal-modal.file-picker h2] */
    margin: -1rem 0 1rem 0 !important;
}
</style>
