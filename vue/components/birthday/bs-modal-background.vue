<script setup lang="ts">
/**
 *
 */

const canvasWidth = window.Vue.ref(500);
const canvasHeight = window.Vue.ref(500);

/**
 * Initialize the animations when the component is mounted
 */
window.Vue.onMounted(() => {});
</script>

<template>
    <canvas :width="canvasWidth" :height="canvasHeight"></canvas>
</template>

<style scoped></style>
