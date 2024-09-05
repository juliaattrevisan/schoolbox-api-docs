<script setup lang="ts">
/**
 * When it's a user's birthday, a special notification bell will appear in the navigation bar
 * that can be selected to display a birthday message.
 */

import { gsap } from "gsap/all";
import { init, destroy } from "./bsNotificationStars";

const emit = defineEmits<{
    selected: [];
}>();

const notificationEl = window.Vue.ref<HTMLDivElement | null>(null);
const canvasEl = window.Vue.ref<HTMLCanvasElement | null>(null);
const duration = window.Vue.ref(1.5);

/**
 * Append the birthday notification to the notifications list
 */
function appendToNotifications(): void {
    const toggle = document.querySelector("#notifications") as HTMLAnchorElement;
    if (toggle) {
        toggle.appendChild(notificationEl.value as HTMLDivElement);
    }
}

/**
 * Animate stars behind the birthday bell
 */
function animateStars(): void {
    init(canvasEl.value as HTMLCanvasElement);
}

/**
 * Animate the birthday bell, swinging it back and forth
 */
function animateBell(): void {
    const bell = document.querySelector("[data-birthday-bell]");
    gsap.set(bell, { rotate: 50 });
    gsap.to(bell, {
        rotate: -50,
        duration: duration.value,
        ease: "power2.inOut",
        transformOrigin: "center top",
        repeat: -1,
        yoyo: true,
    });
}

/**
 * Emit so the birthday modal can be shown
 */
function onSelected(): void {
    destroy(canvasEl.value as HTMLCanvasElement); // destroy the canvas so it doesn't keep animating
    notificationEl.value?.remove(); // remove once it's pressed so the usual notifications can be shown
    emit("selected");
}

/**
 * Append the birthday notification to the
 * notifications list and start animating
 */
window.Vue.onMounted(() => {
    appendToNotifications();
    animateStars();
    animateBell();
});
</script>

<template>
    <button ref="notificationEl" class="birthday-button" @click.prevent="onSelected">
        <div class="birthday-container">
            <div class="birthday-container__background">
                <canvas ref="canvasEl" width="72" height="72"></canvas>
            </div>
            <div class="birthday-container__foreground l-flex l-flex--center">
                <sbx-icon
                    name="notifications"
                    color="var(--navigation-foreground)"
                    size="2rem"
                    data-birthday-bell
                ></sbx-icon>
            </div>
        </div>
    </button>
</template>

<style lang="scss" scoped>
.birthday-button {
    margin: 0; // to remove the default button margin
    padding: 0; // to remove the default button padding
    position: absolute; // to overlay the notification bar
    top: 0;
}

.birthday-container {
    background: var(--navigation-background);
    display: grid;
    height: 4.5rem;
    line-height: normal;
    overflow: hidden;
    width: 4.5rem;
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
