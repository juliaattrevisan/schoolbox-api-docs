<script setup lang="ts">
/**
 * The start of a new toast system.
 * Once this is complete, the old toast system will be removed.
 * For now it's just a drop in replacement for the existing toast system,
 * with a new way to call the toast using document.dispatchEvent.
 */

import { ToastActivateEventDetail, ToastLink, ToastTimeoutEventDetail, ToastEvent } from "./sbxToastEvent";

const STATE_HIDDEN = "hidden";
const STATE_PENDING = "pending";
const STATE_DONE = "done";
const STATE_FAILED = "failed";

const DEFAULT_FADE_DURATION = 3000;
const DEFAULT_DISPLAY_DURATION_SUCCESSFUL = 3000;
const DEFAULT_DISPLAY_DURATION_UNSUCCESSFUL = 5000;

const text = window.Vue.ref("");
const state = window.Vue.ref(STATE_HIDDEN);
const link = window.Vue.ref<ToastLink | null>(null);
const fadeDuration = window.Vue.ref(DEFAULT_FADE_DURATION);
const hidden = window.Vue.ref(true);

/**
 * Activate this toast.
 * @param $text - The text to put in it
 * @param $link - The link object, what it should say, where it should go
 */
function activate($text: string, $link: ToastLink | null): void {
    link.value = $link;
    text.value = $text;
    state.value = STATE_PENDING;
    hidden.value = false;
}

/**
 * Display this toast with a timeout.
 * @param $text - The text to put in it
 * @param [isSuccess] -  Was the operation that displays this toast
 * @param [$duration] - How long should the toast show?
 * @param [$fadeDuration] - How long to wait before the toast disappears? (in ms)
 * @param [timeoutCallback] If callable, call this
 *                        when the toast times out
 */
function timeout(
    $text: string,
    isSuccess?: boolean,
    $duration?: number,
    $fadeDuration?: number,
    timeoutCallback?: () => void,
): void {
    text.value = $text;
    state.value = isSuccess || isSuccess === undefined ? STATE_DONE : STATE_FAILED;
    let duration;
    if ($duration === undefined) {
        duration = isSuccess ? DEFAULT_DISPLAY_DURATION_SUCCESSFUL : DEFAULT_DISPLAY_DURATION_UNSUCCESSFUL;
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

/**
 * Listen for toast events
 */
window.Vue.onMounted(() => {
    document.addEventListener(ToastEvent.ACTIVATE, (event) => {
        const eventDetail = (event as CustomEvent).detail as ToastActivateEventDetail;
        activate(eventDetail.text, eventDetail.link ?? null);
    });
    document.addEventListener(ToastEvent.TIMEOUT, (event) => {
        const eventDetail = (event as CustomEvent).detail as ToastTimeoutEventDetail;
        timeout(
            eventDetail.text,
            eventDetail.isSuccess,
            eventDetail.duration,
            eventDetail.fadeDuration,
            eventDetail.timeoutCallback,
        );
    });
});
</script>

<template>
    <div
        v-if="state !== STATE_HIDDEN"
        :class="{ pop: !hidden, toast: true, alert: state === STATE_FAILED, success: state === STATE_DONE }"
        :style="{ opacity: 1 }"
        data-test="toast"
        :data-test-is-success="state === STATE_DONE"
    >
        {{ text }}
        <a v-if="link !== null" :href="link.href">{{ link.text }}</a>
    </div>
</template>

<style scoped></style>
