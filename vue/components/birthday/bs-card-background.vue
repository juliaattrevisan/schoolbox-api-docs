<script setup lang="ts">
/**
 * To display the stars behind the cake and text in the birthday card
 */

import { init, destroy } from "./bsCardStars";

const canvasEl = window.Vue.ref<HTMLCanvasElement | null>(null);
const canvasWidth = window.Vue.ref(0);
const canvasHeight = window.Vue.ref(0);
const modalContainer = document.querySelector("[data-modal-container-foreground]") as HTMLDivElement; // so we can keep the canvas size in sync
let resizeObserver: ResizeObserver;

/**
 * Set the canvas size to the size of the modal
 */
function setCanvasSize(): void {
    const modalContainerDimensions = modalContainer.getBoundingClientRect();
    canvasWidth.value = Math.round(modalContainerDimensions.width);
    canvasHeight.value = Math.round(modalContainerDimensions.height);
}

/**
 * Animate stars behind the birthday cake and text
 */
function animateStars(): void {
    init(canvasEl.value as HTMLCanvasElement);
}

/**
 * To sync up the canvas size with the size of the modal
 */
function initResizeObserver(): void {
    resizeObserver = new ResizeObserver(() => {
        setCanvasSize();
        animateStars();
    });
    resizeObserver.observe(modalContainer);
}

/**
 * When the component is mounted, set the canvas size and animate the stars
 */
window.Vue.onMounted(async () => {
    await window.Vue.nextTick();
    setCanvasSize();
    initResizeObserver();
    animateStars();
});

/**
 * When the component is unmounted, destroy the canvas
 */
window.Vue.onBeforeUnmount(() => {
    resizeObserver.disconnect();
    destroy(canvasEl.value as HTMLCanvasElement);
});
</script>

<template>
    <canvas ref="canvasEl" :width="canvasWidth" :height="canvasHeight"></canvas>
</template>

<style scoped></style>
