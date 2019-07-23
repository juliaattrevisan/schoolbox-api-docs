<template>
    <div class="reveal-modal" data-reveal aria-hidden="true" role="dialog" :id="elementId">
        <slot></slot>
    </div>
</template>

<script>
    import ucid from 'ucid';
    export default {
        name: "reveal-modal",
        mixins: [
            ucid,
        ],
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
        },
    }
</script>

<style scoped>
</style>
