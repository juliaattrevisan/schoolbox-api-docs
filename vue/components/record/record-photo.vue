<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To take and view a photo using the browser or device capture
 *
 */

import RecordPhotoDevice from "@schoolboxVue/components/inputCapture/record-photo-device";
import RecordPhotoBrowser from "@schoolboxVue/components/videojs-record/record-photo-browser";
import { hasDeviceCapture } from "@js/util/featureDetectionUtils";

export default {
    components: {
        RecordPhotoDevice,
        RecordPhotoBrowser,
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
    <record-photo-device
        v-if="hasDeviceCapture"
        :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
        v-bind="$attrs"
    />
    <record-photo-browser v-else :pre-recorded-data="preRecordedData ? preRecordedData : undefined" v-bind="$attrs" />
</template>
