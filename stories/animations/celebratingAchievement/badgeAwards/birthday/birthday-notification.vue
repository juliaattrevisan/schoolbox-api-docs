<script setup lang="ts">
/**
 *
 */

import { gsap } from "gsap/all";

/**
 * Animate the birthday bell
 */
function animateBell() {
    const birthdayBell = document.querySelector("[data-birthday-bell]");
    gsap.set(birthdayBell, { rotate: 50 });
    gsap.to(birthdayBell, { rotate: -50, duration: 1.3, ease: "power4.inOut", repeat: -1, yoyo: true });
}

window.Vue.onMounted(() => {
    animateBell();
});
</script>

<template>
    <div class="c-birthday-notification l-flex l-flex--center">
        <sbx-icon name="notifications" color="var(--navigation-foreground)" size="1.5rem" data-birthday-bell></sbx-icon>
    </div>
</template>

<style scoped>
.c-birthday-notification {
    background: var(--navigation-background);
    height: 4.5rem;
    width: 4.5rem;
}

sbx-icon {
    transform-origin: center top;
    transition: 500ms;
}
</style>
