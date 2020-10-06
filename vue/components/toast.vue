<script>

    const STATE_HIDDEN  = 'hidden';
    const STATE_PENDING = 'pending';
    const STATE_DONE    = 'done';
    const STATE_FAILED  = 'failed';

    export default {
        name: "toast",
        data() {
            return {
                text:         '',
                state:        STATE_HIDDEN,
                fadeDuration: 2000,
                hidden:       true,
            };
        },
        methods: {
            /**
             * Activate this toast.
             *
             * @param text The text to put in it
             */
            activate(text) {
                this.text   = text;
                this.state  = STATE_PENDING;
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
             */
            timeout(text, successful, duration, fadeDuration) {
                let self   = this;
                this.text  = text;
                this.state = successful ? STATE_DONE : STATE_FAILED;
                if (fadeDuration) {
                    this.fadeDuration = fadeDuration;
                }
                setTimeout(
                    function () {
                        self.state  = STATE_HIDDEN;
                        self.hidden = true;
                    },
                    duration
                )
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
    }
</script>

<template>
    <transition name="fade" :duration="{ enter: fadeDuration, leave: fadeDuration }">
        <div ref="toast" v-if="!isHidden" :class="{toast: true, alert: isFailed, success: isDone}" :style="{opacity: 1}">
            {{ text }}
        </div>
    </transition>
</template>
