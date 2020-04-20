<template>
    <div class="reveal-modal small" data-reveal aria-hidden="true" role="dialog" :id="elementId">
        <slot></slot>
        <a class="close-reveal-modal" aria-label="Close">&#215;</a>
    </div>
</template>

<script>
    let ucid = 0;
    export default {
        name: "reveal-modal",
        beforeCreate() {
            this.ucid = ucid.toString();
            ucid++;
        },
        data() {
            return {
                elementId: 'reveal-modal-' + String(this.ucid)
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
