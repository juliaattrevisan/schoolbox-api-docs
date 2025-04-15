<script setup lang="ts">
/**
 * The start of a new toast system.
 * Once this is complete, the old toast system will be removed.
 * For now it's just a drop in replacement for the existing toast system,
 * with a new way to call the toast using document.dispatchEvent.
 */

import { ToastState } from "./sbxToastEvent";
import type { Toast, ToastActivateEventDetail, ToastTimeoutEventDetail } from "./sbxToastEvent";

const DEFAULT_FADE_DURATION = 3000;
const DEFAULT_DISPLAY_DURATION_SUCCESSFUL = 3000;
const DEFAULT_DISPLAY_DURATION_UNSUCCESSFUL = 5000;

const activeToast = window.Vue.ref<Toast | null>(null);

/**
 * Activate a toast
 * @param toast - The toast instance
 * @param detail - The details of the toast
 * @returns The toast instance
 */
function activate(toast: Toast, detail: ToastActivateEventDetail): Toast {
    toast.text = detail.text;
    toast.link = detail.link ?? null;
    toast.state = ToastState.PENDING;
    toast.hidden = false;

    // this is now the active toast...
    activeToast.value = toast;
    return activeToast.value;
}

/**
 * Timeout a toast
 * @param toast - The toast instance
 * @param _detail - The details of the timeout
 * @returns The toast instance
 */
function timeout(toast: Toast, _detail?: ToastTimeoutEventDetail): Toast {
    const detail: ToastTimeoutEventDetail = _detail ?? { text: toast.text };

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
    toast.state = detail.isSuccess ? ToastState.DONE : ToastState.FAILED;
    toast.fadeDuration = detail.fadeDuration ?? DEFAULT_FADE_DURATION;

    let duration: number;
    if (detail.duration === undefined) {
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
            activeToast.value = null;
        }, duration),
        callback: detail.timeoutCallback,
    };

    return toast;
}

/**
 * Create (and activate) a new toast
 * Since we currently only support one toast at a time, this will dismiss any existing toast first
 * @param detail - The details of the toast
 * @returns The toast object
 */
function createToast(detail: ToastActivateEventDetail): Toast {
    const toast: Toast = window.Vue.reactive({
        text: detail.text,
        state: ToastState.PENDING,
        link: null,
        fadeDuration: DEFAULT_FADE_DURATION,
        hidden: true,
        currentTimeout: null,
        activate: (_detail: ToastActivateEventDetail) => activate(toast, _detail),
        timeout: (_detail?: ToastTimeoutEventDetail) => timeout(toast, _detail),
    });

    return toast.activate(detail);
}

/**
 * Listen for toast events
 */
window.Vue.onMounted(() => {
    window.sbProps.toast = {
        activate: createToast,
        timeout: (detail?: ToastTimeoutEventDetail): Toast | null => {
            if (!activeToast.value) {
                return null;
            }
            return activeToast.value.timeout(detail);
        },
    };
});
</script>

<template>
    <div
        v-if="activeToast && activeToast.state !== ToastState.HIDDEN"
        :class="{
            pop: !activeToast.hidden,
            toast: true,
            alert: activeToast.state === ToastState.FAILED,
            success: activeToast.state === ToastState.DONE,
        }"
        :style="{ opacity: 1 }"
        data-test="toast"
        :data-test-is-success="activeToast.state === ToastState.DONE"
    >
        {{ activeToast.text }}
        <a v-if="activeToast.link !== null" :href="activeToast.link.href">{{ activeToast.link.text }}</a>
    </div>
</template>

<style scoped></style>
