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
            };
        },
        methods: {
            activate(text) {
                this.text  = text;
                this.state = STATE_PENDING;
            },
            timeout(text, successful, duration, fadeDuration) {
                this.text         = text;
                this.state        = successful ? STATE_DONE : STATE_FAILED;
                this.fadeDuration = fadeDuration;
                setTimeout(
                    function () {
                        this.state = STATE_HIDDEN;
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
    <transition name="fade" duration="{ leave: fadeDuration }">
        <div ref="toast" v-if="!isHidden" :class="{toast: true, alert: isFailed, done: isDone}">
            {{ text }}
        </div>
    </transition>
</template>

<style scoped>
    /* Styles are in a global scss file for now: there they may stay */
</style>
