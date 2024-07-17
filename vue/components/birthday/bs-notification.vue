<script setup lang="ts">
/**
 * When it's a user's birthday, a notification will appear in the navigation bar.
 */

import { gsap } from "gsap/all";
import { init } from "./birthdayNotificationStreamers";

/**
 * Animate streamers behind the birthday bell
 */
function animateStreamers() {
    const streamersCanvas = document.querySelector("[data-birthday-bell-streamers]") as HTMLCanvasElement;
    init(streamersCanvas);
}

/**
 * Animate the birthday bell
 */
function animateBell() {
    const bell = document.querySelector("[data-birthday-bell]");
    gsap.set(bell, { rotate: 50 });
    gsap.to(bell, {
        rotate: -50,
        duration: 1.5,
        ease: "power2.inOut",
        transformOrigin: "center top",
        repeat: -1,
        yoyo: true,
    });
}

/**
 * Initialize the animations when the component is mounted
 */
window.Vue.onMounted(() => {
    animateStreamers();
    animateBell();
});
</script>

<template>
    <div class="c-birthday-notification">
        <div class="c-birthday-notification__background">
            <canvas width="72" height="72" data-birthday-bell-streamers></canvas>
        </div>
        <div class="c-birthday-notification__foreground l-flex l-flex--center">
            <sbx-icon
                name="notifications"
                color="var(--navigation-foreground)"
                size="2rem"
                data-birthday-bell
            ></sbx-icon>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.c-birthday-notification {
    background: var(--navigation-background);
    display: grid;
    height: 4.5rem;
    overflow: hidden;
    width: 4.5rem;
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
