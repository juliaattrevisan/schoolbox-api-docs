<script setup lang="ts">
/**
 * To display the stars behind the cake and text in the birthday modal
 */

import { init } from "./birthdayModalStars";

const canvasWidth = window.Vue.ref(998);
const canvasHeight = window.Vue.ref(840);
const modalContainer = document.querySelector("[data-modal-container]") as HTMLDivElement;

/**
 * Set the canvas size to the size of the modal
 */
function setCanvasSize() {
    const modalContainerDimensions = modalContainer.getBoundingClientRect();
    canvasWidth.value = Math.round(modalContainerDimensions.width);
    canvasHeight.value = Math.round(modalContainerDimensions.height);
}

/**
 * To sync up the canvas size with the size of the modal
 */
function initResizeObserver() {
    const resizeObserver = new ResizeObserver(() => {
        setCanvasSize();
    });
    resizeObserver.observe(modalContainer);
}

/**
 * Animate stars behind the birthday cake and text
 */
function animateStars() {
    const starsCanvas = document.querySelector("[data-birthday-card-stars]") as HTMLCanvasElement;
    init(starsCanvas);
}

/**
 * When the component is mounted, set the canvas size and animate the stars
 */
window.Vue.onMounted(() => {
    setCanvasSize();
    initResizeObserver();
    animateStars();
});
</script>

<template>
    <canvas :width="canvasWidth" :height="canvasHeight" data-birthday-card-stars></canvas>
</template>

<style scoped></style>
