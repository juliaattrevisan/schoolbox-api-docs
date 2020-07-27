<script>
    import RevealModal from '@schoolbox/reveal-modal';
    import ClipboardJs from 'clipboard/dist/clipboard';

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
                new ClipboardJs(
                    this.$refs.button,
                    {
                        target: () => this.$refs.urlInput,
                    }
                )
            },
        },
    }
</script>

<template>
    <reveal-modal ref="modal">
        <p>Use the following address to subscribe your favourite calendar application to a live feed.</p>
        <textarea ref="urlInput" readonly rows="6" v-text="url"></textarea>
        <div class="component-action">
            <section>
                <button ref="button">Copy to Clipboard</button>
            </section>
        </div>
    </reveal-modal>
</template>
