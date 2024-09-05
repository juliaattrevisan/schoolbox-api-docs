<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import RevealModal from "@schoolboxVue/components/reveal-modal";
import ClipboardJs from "clipboard/dist/clipboard";

export default {
    name: "export-url-modal",

    components: { RevealModal },

    props: ["url"],

    data() {
        return {
            clipboardJs: null,
        };
    },

    /**
     * On mount: instantiate the clipboardJS object
     */
    mounted() {
        this.clipboardJs = new ClipboardJs(this.$refs.button, {
            target: () => this.$refs.urlInput,
        });
    },

    /**
     * On destroy: clean up the clipboardJS object
     */
    beforeUnmount() {
        this.clipboardJs.destroy();
    },

    methods: {
        /**
         * Open this modal.
         */
        open() {
            this.$refs.modal.open();
        },
    },
};
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
