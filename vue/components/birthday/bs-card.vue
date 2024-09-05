<script setup lang="ts">
/**
 * The 'card' that appears within a modal when it's a user's birthday.
 * It's triggered by selecting the birthday notification bell in the
 * navigation bar.
 */

import { gsap, CustomEase, CustomBounce } from "gsap/all";
import BsCardBackground from "./bs-card-background.vue";
import BsCardForeground from "./bs-card-foreground.vue";

// eslint-disable-next-line @typescript-eslint/no-unsafe-argument -- The plugins import as <any> so we need to ignore this
gsap.registerPlugin(CustomEase, CustomBounce); // Register the custom plugins otherwise they won't work

const hasRevealed = window.Vue.ref(false);
</script>

<template>
    <div class="modal-container l-pad-s">
        <div class="modal-container__background">
            <bs-card-background v-if="hasRevealed" />
        </div>
        <div class="modal-container__foreground" data-modal-container-foreground>
            <bs-card-foreground @revealed="hasRevealed = true" />
        </div>
    </div>
</template>

<style lang="scss" scoped>
.modal-container {
    display: grid;
    overflow: hidden;
    &__background,
    &__foreground {
        grid-area: 1/-1; // place the background/foreground on top of each other
    }
    &__background {
        z-index: var(--sbx-depth-default);
    }
    &__foreground {
        z-index: calc(var(--sbx-depth-default) + 1);
    }
}
</style>
