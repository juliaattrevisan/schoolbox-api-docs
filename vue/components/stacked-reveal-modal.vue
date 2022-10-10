<script>

import { generateUUID } from '@js/util/jsUtils';

// Valid sizes from https://get.foundation/sites/docs-v5/bundle/reveal.html
const VALID_SIZES = ['tiny', 'small', 'medium', 'large', 'xlarge', 'full'];

export default {

    emits: ['closed'],

    props: {
        /**
         * The size of the modal to display.
         *
         * This is added as a class to the modal.
         */
        size: {
            type: String,
            default: 'small',
            validator: (value) => VALID_SIZES.indexOf(value) !== -1
        },

        /**
         * Is the modal closed when the background is clicked?
         *
         * This corresponds to the modal's `close_on_background_click`
         * setting.
         *
         * @see https://get.foundation/sites/docs-v5/bundle/reveal.html
         */
        closeOnBackgroundClick: {
            type: Boolean,
            default: true,
        },

        /**
         * For when closing the modal needs to be temporarily disabled,
         * for example when an action is processing.
         */
        isCloseDisabled: {
            type: Boolean,
            default: false,
        },
    },

    data () {
        return {
            elementId: `reveal-modal-${generateUUID()}`,
        }
    },

    mounted () {
        // How do I find the dialog in the DOM? ID probably needs to be unique
        this.modal = $(`#1-${this.elementId}`);
    },

    methods: {

        /**
         * Open this modal.
         */
        open () {
            this.modal.foundation(
                'reveal',
                'open',
                { close_on_background_click: this.closeOnBackgroundClick }
            );
            $(document).on('closed.fndtn.reveal', '[data-reveal]', (event) => { //to respond to the modal closing when requried
                if (event.target.id.includes(this.elementId)) { //can be multiple modals open at once
                    this.$emit('closed');
                    $(document).off('closed.fndtn.reveal');
                }
            });
        },

        /**
         * To prevent the modal being closed when it should be disabled
         *
         * @param { Event } select event
         */
        onCloseSelected (event) {
            if (this.isCloseDisabled) {
                event.stopImmediatePropagation();
            }
        },

        /**
         * Close this modal.
         */
        close () {
            this.modal.foundation('reveal', 'close');
        },

    },

}
</script>

<template>
    <div :id="`1-${elementId}`" :class="`reveal-modal ${size}`" data-reveal aria-hidden="true" role="dialog">
        <!-- @slot Reveal modal content goes into this slot -->
        <slot name="reveal-1">Stacked Reveal 1</slot>
        <a class="close-reveal-modal" aria-label="Close" @click.prevent="onCloseSelected">&#215;</a>
    </div>
    <div :id="`2-${elementId}`" :class="`reveal-modal ${size}`" data-reveal :data-reveal-reopen="`1-${elementId}`" aria-hidden="true" role="dialog">
        <!-- @slot Reveal modal content goes into this slot -->
        <slot name="reveal-2">Stacked Reveal 2</slot>
        <a class="close-reveal-modal" aria-label="Close" @click.prevent="onCloseSelected">&#215;</a>
    </div>
</template>

<style lang="scss" scoped>

.xlarge {
    max-width: 80rem; //make wider for user-bulk-modify-new-values.vue
}

</style>