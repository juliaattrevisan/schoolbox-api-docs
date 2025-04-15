<script setup lang="ts">
/**
 * The start of a new toast system.
 * Once this is complete, the old toast system will be removed.
 * For now it's just a drop in replacement for the existing toast system,
 * with a new way to call the toast using document.dispatchEvent.
 */

import type { ToastActivateEventDetail, ToastLink, ToastTimeoutEventDetail } from "./sbxToastEvent";

enum ToastState {
    HIDDEN = "hidden",
    PENDING = "pending",
    DONE = "done",
    FAILED = "failed",
}

const DEFAULT_FADE_DURATION = 3000;
const DEFAULT_DISPLAY_DURATION_SUCCESSFUL = 3000;
const DEFAULT_DISPLAY_DURATION_UNSUCCESSFUL = 5000;

const text = window.Vue.ref("");
const state = window.Vue.ref(ToastState.HIDDEN);
const link = window.Vue.ref<ToastLink | null>(null);
const fadeDuration = window.Vue.ref(DEFAULT_FADE_DURATION);
const hidden = window.Vue.ref(true);
const activeTimeout = window.Vue.ref<{
    timeoutId: number;
    callback?: () => void;
} | null>(null);

/**
 * Dismiss the active toast, and run the callback if there is one
 */
function dismiss(): void {
    state.value = ToastState.HIDDEN;
    hidden.value = true;

    // If there's a callback to run, run it
    if (activeTimeout.value && activeTimeout.value.callback instanceof Function) {
        activeTimeout.value.callback();
    }
    activeTimeout.value = null;
}

/**
 * Activate this toast.
 * @param $text - The text to put in it
 * @param $link - The link object, what it should say, where it should go
 */
function activate($text: string, $link: ToastLink | null): void {
    // dismiss any existing toast first (as there can only be one at a time)
    dismiss();

    link.value = $link;
    text.value = $text;
    state.value = ToastState.PENDING;
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
    state.value = isSuccess || isSuccess === undefined ? ToastState.DONE : ToastState.FAILED;
    let duration;
    if ($duration === undefined) {
        duration = isSuccess ? DEFAULT_DISPLAY_DURATION_SUCCESSFUL : DEFAULT_DISPLAY_DURATION_UNSUCCESSFUL;
    } else {
        duration = $duration;
    }
    if ($fadeDuration) {
        fadeDuration.value = $fadeDuration;
    }

    activeTimeout.value = {
        timeoutId: window.setTimeout(dismiss, duration),
        callback: timeoutCallback,
    };
}

/**
 * Listen for toast events
 */
window.Vue.onMounted(() => {
    window.sbProps.toast = {
        activate: (detail: ToastActivateEventDetail): void => {
            activate(detail.text, detail.link ?? null);
        },
        timeout: (detail: ToastTimeoutEventDetail): void => {
            timeout(detail.text, detail.isSuccess, detail.duration, detail.fadeDuration, detail.timeoutCallback);
        },
    };
});
</script>

<template>
    <div
        v-if="state !== ToastState.HIDDEN"
        :class="{ pop: !hidden, toast: true, alert: state === ToastState.FAILED, success: state === ToastState.DONE }"
        :style="{ opacity: 1 }"
        data-test="toast"
        :data-test-is-success="state === ToastState.DONE"
    >
        {{ text }}
        <a v-if="link !== null" :href="link.href">{{ link.text }}</a>
    </div>
</template>

<style scoped></style>
