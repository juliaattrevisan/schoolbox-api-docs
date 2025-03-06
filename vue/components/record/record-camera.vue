<script>
/* eslint-disable eslint-comments/disable-enable-pair -- see below */
/* eslint-disable eslint-comments/no-unlimited-disable -- see below */
/* eslint-disable -- Ignore this file for ESLint checks for now (SA-18376) */
/* eslint-enable vue/no-v-html */
/**
 * To take and view a recording using the browser or device capture
 *
 */

import RecordCameraDevice from "@schoolboxVue/components/inputCapture/record-camera-device";
import RecordCameraBrowser from "@schoolboxVue/components/videojs-record/record-camera-browser";
import { hasDeviceCapture } from "@js/util/featureDetectionUtils";

export default {
    components: {
        RecordCameraDevice,
        RecordCameraBrowser,
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
        this.hasDeviceCapture = hasDeviceCapture();
    },
};
</script>

<template>
    <record-camera-device
        v-if="hasDeviceCapture"
        :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
        v-bind="$attrs"
    />
    <record-camera-browser v-else :pre-recorded-data="preRecordedData ? preRecordedData : undefined" v-bind="$attrs" />
</template>
