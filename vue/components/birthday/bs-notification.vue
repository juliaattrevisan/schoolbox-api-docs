<script setup lang="ts">
/**
 * When it's a user's birthday, a notification will appear in the navigation bar.
 */

import { gsap } from "gsap/all";
import { init } from "./birthdayNotificationStreamers";

const emit = defineEmits<{
    selected: [];
}>();

const notificationEl = window.Vue.ref<HTMLDivElement | null>(null);
const duration = window.Vue.ref(1.5);

/**
 * Append the birthday notification to the notifications list
 */
function appendToNotifications() {
    const toggle = document.querySelector("#notifications") as HTMLAnchorElement;
    toggle.appendChild(notificationEl.value);
}

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
        duration: duration.value,
        ease: "power2.inOut",
        transformOrigin: "center top",
        repeat: -1,
        yoyo: true,
    });
}

/**
 * Handle the click event when the notification is selected
 */
function onSelected() {
    notificationEl.value?.remove(); // remove once it's pressed so the usual notifications can be shown
    emit("selected");
}

/**
 * Initialize the animations when the component is mounted
 */
window.Vue.onMounted(() => {
    appendToNotifications();
    animateStreamers();
    animateBell();
});
</script>

<template>
    <button ref="notificationEl" class="birthday-button" @click.prevent="onSelected">
        <div class="birthday-container">
            <div class="birthday-container__background">
                <canvas width="72" height="72" data-birthday-bell-streamers></canvas>
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
        z-index: var(--depth-default);
    }
    &__foreground {
        z-index: calc(var(--depth-default) + 1);
    }
}
</style>
