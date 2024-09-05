<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To take a screen recording on desktop, mobile not currently possible
 *
 */

import RecordScreenDevice from "@schoolboxVue/components/inputCapture/record-screen-device";
import RecordScreenBrowser from "@schoolboxVue/components/videojs-record/record-screen-browser";
import { hasDeviceCapture } from "@js/util/featureDetectionUtils";

export default {
    components: {
        RecordScreenDevice,
        RecordScreenBrowser,
    },

    props: {
        preRecordedData: {
            //to display an already existing recording on startup
            type: Object,
            required: false,
        },
    },

    created() {
        //these vars are created so they can be accessed within the template
        this.hasDeviceCapture = hasDeviceCapture(); //to determine which capture mode to use, browser or device
    },
};
</script>

<template>
    <record-screen-device v-if="hasDeviceCapture" v-bind="$attrs" />
    <record-screen-browser v-else :pre-recorded-data="preRecordedData ? preRecordedData : undefined" v-bind="$attrs" />
</template>
