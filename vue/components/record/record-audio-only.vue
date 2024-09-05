<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To record audio only, input devices are unreliable, so is purely browser capture only
 *
 */

import RecordAudioOnlyBrowser from "@schoolboxVue/components/videojs-record/record-audio-only-browser";

export default {
    components: {
        RecordAudioOnlyBrowser,
    },

    props: {
        preRecordedData: {
            //to display an already existing recording on startup
            type: Object,
            required: false,
        },
    },
};
</script>

<template>
    <record-audio-only-browser :pre-recorded-data="preRecordedData ? preRecordedData : undefined" v-bind="$attrs" />
</template>
