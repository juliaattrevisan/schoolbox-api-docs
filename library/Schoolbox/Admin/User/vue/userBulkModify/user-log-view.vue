<script>

/**
 * Display Log files to users
 */

import RevealModal from '@schoolboxVue/components/reveal-modal';


export default {

    components: {
        RevealModal,
    },

    props: {
    },

    data () {
        return {
            hasDataError: false,
            logData: [], //to display the output of the returned action
        }
    },

    computed: {
    },

    methods: {

        /**
         * emit listener
         */
        displayLog (logData, hasDataError) {
            this.logData = logData;
            this.hasDataError = hasDataError;
            this.onLogOpenSelected();
        },

        /**
         * Open the log modal
         */
        onLogOpenSelected () {
            this.$refs.outputLogModal.open();
        },
        /**
         * Close the log modal
         */
        onLogCloseSelected () {
            this.$refs.outputLogModal.close();
        }

    },

}
</script>

<template>
    <div>
        <reveal-modal ref="outputLogModal">
            <div class="output-log">
                <div v-if="hasDataError" class="alert-box alert">The Bulk Modify has failed to update. Further details are logged below.</div>
                <textarea v-html="logData"></textarea>
            </div>
            <div>
                <button @click.prevent="onLogCloseSelected" class="button submit">Close</button>
            </div>
        </reveal-modal>
    </div>
</template>

<style lang="scss">

textarea { //to prevent the potential for a massively long modal
    min-height: 200px;
    max-height: 600px;
}

</style>
