<script setup lang="ts">
/**
 * The start of a new toast system.
 * Once this is complete, the old toast system will be removed.
 * For now it's just a drop in replacement for the existing toast system,
 * with a new way to call the toast using document.dispatchEvent.
 */

import { ToastState } from "./sbxToastTypes";
import type { Toast, ToastLink, ToastTimeoutDetail } from "./sbxToastTypes";

const DEFAULT_FADE_DURATION = 3000; // Units: milliseconds.
const DEFAULT_DISPLAY_DURATION_SUCCESSFUL = 3000; // Units: milliseconds.
const DEFAULT_DISPLAY_DURATION_UNSUCCESSFUL = 5000; // Units: milliseconds.

/**
 * All toasts on the page
 * Currently, we only support one toast at a time - but this makes it easy to change later
 */
const toasts = window.Vue.ref<Toast[]>([]);

/**
 * Activate a toast
 * @param toast - The toast instance
 * @param text - The text to display in the toast
 * @param link - An optional link for the toast to be clickable
 * @returns The toast instance
 */
function activate(toast: Toast, text: string, link?: ToastLink): Toast {
    // dismiss any existing toast first (as currently there can only be one at a time)
    toasts.value.filter((t) => !t.hidden).forEach((t) => t.timeout({ text: t.text, duration: 0 }));

    toast.text = text;
    toast.link = link ?? null;
    toast.state = ToastState.PENDING;
    toast.hidden = false;

    toasts.value.push(toast);
    return toast;
}

/**
 * Timeout a toast
 * @param toast - The toast instance
 * @param _detail - The details of the timeout
 * @returns The toast instance
 */
function timeout(toast: Toast, _detail?: ToastTimeoutDetail): Toast {
    const detail: ToastTimeoutDetail = _detail ?? { text: toast.text };

    /**
     * If the toast was already told to timeout, clear that timeout
     * and immediately run the old callback if there was one
     */
    if (toast.currentTimeout) {
        window.clearTimeout(toast.currentTimeout.timeoutId);

        if (toast.currentTimeout.callback instanceof Function) {
            toast.currentTimeout.callback();
        }
    }

    toast.text = detail.text ?? toast.text;
    toast.state = detail.isSuccess || detail.isSuccess === undefined ? ToastState.DONE : ToastState.FAILED;
    toast.fadeDuration = detail.fadeDuration ?? DEFAULT_FADE_DURATION;

    let duration: number;
    if (toast.hidden) {
        duration = 0; // If this toast is already hidden, immediately run the callback
    } else if (detail.duration === undefined) {
        duration = detail.isSuccess ? DEFAULT_DISPLAY_DURATION_SUCCESSFUL : DEFAULT_DISPLAY_DURATION_UNSUCCESSFUL;
    } else {
        duration = detail.duration;
    }

    toast.currentTimeout = {
        timeoutId: window.setTimeout(() => {
            toast.state = ToastState.HIDDEN;
            toast.hidden = true;

            // If there's a callback to run, run it
            if (toast.currentTimeout && toast.currentTimeout.callback instanceof Function) {
                toast.currentTimeout.callback();
            }
            toast.currentTimeout = null;
        }, duration),
        callback: detail.timeoutCallback,
    };

    return toast;
}

/**
 * Create (and activate) a new toast
 * Since we currently only support one toast at a time, this will dismiss any existing toast first
 * @param text - The text to display in the toast
 * @param link - An optional link for the toast to be clickable
 * @returns The toast object
 */
function createToast(text: string, link?: ToastLink): Toast {
    const toast: Toast = window.Vue.reactive({
        text,
        state: ToastState.PENDING,
        link: null,
        fadeDuration: DEFAULT_FADE_DURATION,
        hidden: true,
        currentTimeout: null,
        activate: (_text: string, _link?: ToastLink) => activate(toast, _text, _link),
        timeout: (_detail?: ToastTimeoutDetail) => timeout(toast, _detail),
    });

    return toast.activate(text, link);
}

/**
 * Listen for toast events
 */
window.Vue.onMounted(() => {
    window.sbProps.toastActivate = createToast;
});
</script>

<template>
    <template v-for="(toast, i) in toasts" :key="i">
        <div
            v-if="toast.state !== ToastState.HIDDEN"
            class="toast"
            :class="{
                pop: !toast.hidden,
                alert: toast.state === ToastState.FAILED,
                success: toast.state === ToastState.DONE,
            }"
            :style="{ opacity: 1 }"
            data-test="toast"
            :data-test-is-success="toast.state === ToastState.DONE"
        >
            {{ toast.text }}
            <a v-if="toast.link !== null" :href="toast.link.href">{{ toast.link.text }}</a>
        </div>
    </template>
</template>

<style scoped></style>
