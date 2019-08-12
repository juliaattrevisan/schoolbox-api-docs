<template>
    <div class="reveal-modal" data-reveal aria-hidden="true" role="dialog" :id="elementId">
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
            this.dialog = this.$('#' + this.elementId);
        },
        computed: {
            $: function () { return window.jQuery; },
        },
        methods: {
            open() {
                this.dialog.foundation('reveal', 'open');
            },
            close() {
                this.dialog.foundation('reveal', 'close');
            },
        },
    }
</script>
