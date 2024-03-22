<script setup lang="ts">
/**
 * The start of a new toast system.
 * Once this is complete, the old toast system will be removed.
 * For now it's just a drop in replacement for the existing toast system,
 * with a new way to call the toast using document.dispatchEvent.
 */

const STATE_HIDDEN = "hidden";
const STATE_PENDING = "pending";
const STATE_DONE = "done";
const STATE_FAILED = "failed";

const DEFAULT_FADE_DURATION = 2000;
const DEFAULT_DISPLAY_DURATION_SUCCESSFUL = 2000;
const DEFAULT_DISPLAY_DURATION_UNSUCCESSFUL = 5000;

const text = window.Vue.ref("");
const state = window.Vue.ref(STATE_HIDDEN);
const fadeDuration = window.Vue.ref(DEFAULT_FADE_DURATION);
const hidden = window.Vue.ref(true);

window.Vue.onMounted(() => {
    console.warn("yep");
    // document.addEventListener("TOAST_ACTIVATE", (event) => {
    //     const { text, successful, duration, fadeDuration, timeoutCallback } = event.detail;
    //     timeout(text, successful, duration, fadeDuration, timeoutCallback);
    // });
});

/**
 * Activate this toast.
 * @param {string} $text - The text to put in it
 */
function activate($text: string) {
    text.value = $text;
    state.value = STATE_PENDING;
    hidden.value = false;
}

/**
 * Display this toast with a timeout.
 * @param {string} $text - The text to put in it
 * @param {boolean} successful -  Was the operation that displays this toast
 * @param {number} $duration - How long should the toast show?
 * @param {number} $fadeDuration - How long to wait before the toast disappears? (in ms)
 * @param {Function|null} timeoutCallback If callable, call this
 *                        when the toast times out
 */
function timeout(
    $text: string,
    successful: boolean,
    $duration: number,
    $fadeDuration: number,
    timeoutCallback: Function | null,
) {
    text.value = $text;
    state.value = successful ? STATE_DONE : STATE_FAILED;
    let duration;
    if ($duration === undefined) {
        duration = successful ? DEFAULT_DISPLAY_DURATION_SUCCESSFUL : DEFAULT_DISPLAY_DURATION_UNSUCCESSFUL;
    } else {
        duration = $duration;
    }
    if ($fadeDuration) {
        fadeDuration.value = $fadeDuration;
    }
    setTimeout(() => {
        state.value = STATE_HIDDEN;
        hidden.value = true;
        if (timeoutCallback instanceof Function) {
            timeoutCallback();
        }
    }, duration);
}
</script>

<template>
    <div
        v-if="state !== STATE_HIDDEN"
        :class="{ pop: !hidden, toast: true, alert: state === STATE_FAILED, success: state === STATE_DONE }"
        :style="{ opacity: 1 }"
    >
        {{ text }}
    </div>
</template>

<style scoped></style>
