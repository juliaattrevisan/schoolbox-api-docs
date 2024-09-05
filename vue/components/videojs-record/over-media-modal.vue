<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/*
 * To notify of various media events, like camera not available or video converting/uploading
 * Just a slot really, the parent puts whatever it needs in it
 * Typically toggled by a v-if in the parent component, which when toggled will animateOn() on mounted()
 *
 */

import { gsap } from "gsap/all";
import recordMediaEvents from "@js/record-media/recordMediaEvents";

export default {
    props: {
        isClosing: {
            //to toggle an animate out
            type: Boolean,
            required: false,
        },
        closedCallBack: {
            //optional method that is notified when the animateOff is complete
            type: Function,
            required: false,
        },
    },

    data() {
        return {
            hasEmitOpened: false, //no need to dispatch closed if opened hasn't been called
        };
    },

    watch: {
        isClosing(newValue) {
            if (newValue) {
                this.animateOff();
            }
        },
    },

    mounted() {
        this.animateOn();
    },

    methods: {
        /*
         * to present the modal
         *
         */
        animateOn() {
            gsap.from(this.$refs.modal, 0.3, { alpha: 0, marginTop: 40, delay: 0 });
            if (this.$refs.modal.offsetHeight > this.$refs.modal.parentElement.offsetHeight) {
                //only if the modal message exceeds the height of the parent container
                this.$emit(
                    recordMediaEvents.OVER_MEDIA_MODAL_OPENED,
                    this.$refs.modal.parentElement.offsetWidth,
                    this.$refs.modal.offsetHeight + 40,
                ); //just ensure that the entire message is visible
                this.hasEmitOpened = true;
            }
        },

        /*
         * to close the modal
         *
         */
        animateOff() {
            gsap.to(this.$refs.modal, 0.2, {
                alpha: 0,
                marginTop: 40,
                ease: "power2.in",
                onComplete: this.closedCallBack,
            });
            if (this.hasEmitOpened) {
                this.$emit(recordMediaEvents.OVER_MEDIA_MODAL_CLOSED); //just ensure that the entire message is visible
                this.hasEmitOpened = false;
            }
        },
    },
};
</script>

<template>
    <div class="modal-container" ref="modalContainer">
        <div class="modal" ref="modal">
            <slot></slot>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.modal-container {
    position: absolute;
    left: 0;
    top: 0;
    z-index: var(--sbx-depth-modal);
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.8);
}

.modal {
    box-sizing: border-box;
    max-width: 90%;
    min-width: 50%;
    padding: 20px;
    background: white;
    button {
        width: 100%;
    }
}

@media screen and (max-width: 640px) {
    .modal {
        padding: 15px 10px;
    }
}
</style>
