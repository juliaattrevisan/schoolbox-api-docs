<template>
    <div :class="'reveal-modal ' + size" data-reveal aria-hidden="true" role="dialog" :id="elementId">
        <slot></slot>
        <a class="close-reveal-modal" aria-label="Close">&#215;</a>
    </div>
</template>

<script>
    let ucid = 0;

    // Valid sizes from https://get.foundation/sites/docs-v5/components/reveal.html
    const VALID_SIZES = ['tiny', 'small', 'medium', 'large', 'xlarge', 'full'];

    export default {
        name: "reveal-modal",
        props: {
            size: {
                type: String,
                default: 'small',
                validator: (value) => VALID_SIZES.indexOf(value) !== -1
            },
        },
        beforeCreate() {
            this.ucid = ucid.toString();
            ucid++;
        },
        data() {
            return {
                elementId: 'reveal-modal-' + String(this.ucid),
            }
        },
        mounted() {
            // How do I find the dialog in the DOM? ID probably needs to be unique
            this.modal = this.jQuery('#' + this.elementId);
        },
        computed: {
            jQuery: () => window.jQuery,
        },
        methods: {
            /**
             * Open this modal.
             */
            open() {
                this.modal.foundation('reveal', 'open');
            },

            /**
             * Close this modal.
             */
            close() {
                this.modal.foundation('reveal', 'close');
            },
        },
    }
</script>
