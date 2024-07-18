<script setup lang="ts">
/**
 * The modal that appears when it's a user's birthday, is triggered by selecting
 * the birthday notification bell in the navigation bar.
 */

import { gsap, CustomEase, CustomBounce } from "gsap/all";
import BsModalBackground from "./bs-modal-background.vue";
import BsModalForeground from "./bs-modal-foreground.vue";

gsap.registerPlugin(CustomEase, CustomBounce); // Register the custom plugins otherwise they won't work
</script>

<template>
    <div class="modal-container">
        <div class="modal-container__background">
            <bs-modal-background />
        </div>
        <div class="modal-container__foreground">
            <bs-modal-foreground />
        </div>
    </div>
</template>

<style lang="scss" scoped>
.modal-container {
    display: grid;
    // height: 100%;
    overflow: hidden;
    // width: 100%;
    &__background,
    &__foreground {
        grid-area: 1/-1; // place the background/foreground on top of each other
    }
    &__background {
        z-index: var(--depth-default);
    }
    &__foreground {
        z-index: calc(var(--depth-default) + 1);
    }
}
</style>
