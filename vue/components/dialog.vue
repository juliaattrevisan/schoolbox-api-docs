<template>
    <div class="reveal-modal" data-reveal aria-hidden="true" role="dialog" :id="'test-' + ucid">
        <slot></slot>
    </div>
</template>

<script>
    import ucid from 'ucid';
    export default {
        name: "dialog",
        mixins: [
            ucid,
        ],
        mounted() {
            // How do I find the dialog in the DOM? ID probably needs to be unique
            this.dialog = $('#' + String(this.ucid));
        },
        computed: {
            $: function () { return window.jQuery; },
        },
        methods: {
            // Added the below openReveal method for two reasons:
            // 1) There was a bug preventing the reveal from working once
            // you navigated away and back to the reveal component.
            // 2) Most dialogs will need to be opened using code.
            open() {
                this.dialog.foundation('reveal', 'open');
            },
        },
    }
</script>

<style scoped>
</style>
