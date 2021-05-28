<script>

/**
 * To take and view a recording using the browser or device capture
 *
 */

import RecordCameraDevice from '@schoolbox/inputCapture/record-camera-device';
import RecordCameraBrowser from '@schoolbox/videojs-record/record-camera-browser';
import { hasDeviceCapture } from '@shared/util/featureDetectionUtils';


export default {

    components: {
        RecordCameraDevice,
        RecordCameraBrowser 
    },

    props: {
        maxFileSize: {
            type: Number, 
            required: true
        },
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
        :max-file-size="maxFileSize"
        :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
        v-on="$listeners"
    />
    <record-camera-browser
        v-else
        :max-file-size="maxFileSize"
        :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
        v-on="$listeners"
    />

</template>