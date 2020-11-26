<template>
    <div :class="'reveal-modal ' + size" data-reveal aria-hidden="true" role="dialog" :id="elementId">
        <slot></slot>
        <a class="close-reveal-modal" aria-label="Close">&#215;</a>
    </div>
</template>

<script>

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
            modalOptions: {
                type: Object,
                default: {},
            }
        },
        beforeCreate() {

            /* Generate a really unique identifier
             * instances of reveal-modal may inadevertently generate conflicting identifier strings
             * when they're based on an autoincrementing integer, since the dom is a universal pool
             * of ids, but reveal-modal ucids aren't shared among each other
             *
             * https://stackoverflow.com/questions/105034/how-to-create-a-guid-uuid
             */
            function uuidv4() {
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                    var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
                    return v.toString(16);
                });
            }

            this.ucid = uuidv4();
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
                this.modal.foundation('reveal', 'open', this.modalOptions);
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
