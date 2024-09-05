<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
const STATE_HIDDEN = "hidden";
const STATE_PENDING = "pending";
const STATE_DONE = "done";
const STATE_FAILED = "failed";

const DEFAULT_FADE_DURATION = 2000;
const DEFAULT_DISPLAY_DURATION_SUCCESSFUL = 2000;
const DEFAULT_DISPLAY_DURATION_UNSUCCESSFUL = 5000;

export default {
    constants: {
        DEFAULT_FADE_DURATION,
        DEFAULT_DISPLAY_DURATION_SUCCESSFUL,
        DEFAULT_DISPLAY_DURATION_UNSUCCESSFUL,
    },
    name: "toast",
    data() {
        return {
            text: "",
            state: STATE_HIDDEN,
            fadeDuration: DEFAULT_FADE_DURATION,
            hidden: true,
        };
    },
    methods: {
        /**
         * Activate this toast.
         *
         * @param text The text to put in it
         */
        activate(text) {
            this.text = text;
            this.state = STATE_PENDING;
            this.hidden = false;
        },

        /**
         * Display this toast with a timeout.
         *
         * @param text         The text to put in it
         * @param successful   Was the operation that displays this toast
         *                     a success? Change the style if it wasn't.
         * @param duration     How long should the toast show?
         * @param fadeDuration How long to wait before the toast disappears?
         * @param {Function|null} timeoutCallback If callable, call this
         *                        when the toast times out
         */
        timeout(text, successful, duration, fadeDuration, timeoutCallback) {
            this.text = text;
            this.state = successful ? STATE_DONE : STATE_FAILED;
            if (duration === undefined) {
                duration = successful ? DEFAULT_DISPLAY_DURATION_SUCCESSFUL : DEFAULT_DISPLAY_DURATION_UNSUCCESSFUL;
            }
            if (fadeDuration) {
                this.fadeDuration = fadeDuration;
            }
            setTimeout(() => {
                this.state = STATE_HIDDEN;
                this.hidden = true;
                if (timeoutCallback instanceof Function) {
                    timeoutCallback();
                }
            }, duration);
        },
    },
    computed: {
        isHidden() {
            return this.state === STATE_HIDDEN;
        },
        isPending() {
            return this.state === STATE_PENDING;
        },
        isDone() {
            return this.state === STATE_DONE;
        },
        isFailed() {
            return this.state === STATE_FAILED;
        },
    },
};
</script>

<template>
    <transition name="fade" :duration="{ enter: fadeDuration, leave: fadeDuration }">
        <div
            v-if="!isHidden"
            ref="toast"
            data-test="toast"
            :class="{ pop: !hidden, toast: true, alert: isFailed, success: isDone }"
            :style="{ opacity: 1 }"
        >
            {{ text }}
        </div>
    </transition>
</template>
