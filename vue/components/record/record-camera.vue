<script>

/**
 * To take and view a recording using the browser or device capture
 *
 */

import RecordCameraDevice from '@vue/components/inputCapture/record-camera-device';
import RecordCameraBrowser from '@vue/components/videojs-record/record-camera-browser';
import { hasDeviceCapture } from '@js/util/featureDetectionUtils';


export default {

    components: {
        RecordCameraDevice,
        RecordCameraBrowser 
    },

    props: {
        preRecordedData: { //to display an already existing recording on startup
            type: Object,
            required: false,
        },
    },

    created () { //these vars are created so they can be accessed within the template
        this.hasDeviceCapture = hasDeviceCapture();
    },

}
</script>

<template>

    <record-camera-device
        v-if="hasDeviceCapture"
        :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
        v-on="$listeners"
    />
    <record-camera-browser
        v-else
        :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
        v-on="$listeners"
    />

</template>