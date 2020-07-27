<script>
    import RevealModal from '@schoolbox/reveal-modal'

    export default {
        name: "export-url-modal",
        props: [
            'url',
        ],
        components: {RevealModal},
        methods: {
            /**
             * Open this modal.
             */
            open() {
                this.$refs.modal.open();
            },
        },
    }
</script>

<template>
    <reveal-modal ref="modal">
        <p>Use the following address to subscribe your favourite calendar application to a live feed.</p>
        <textarea id="export-url-text" readonly rows="6" v-text="url"></textarea>
    </reveal-modal>
</template>
