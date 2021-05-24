<script>

/**
 * To take and view a photo using the browser or device capture
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
       preRecordedData: { //to display an already existing recording on startup
            type: Object,
            required: false,
        },
        isKeepAlive: { //whether the component has to exist between DOM display/redisplay https://vuejs.org/v2/api/#keep-alive - determines whether initiates on mounted() or activated(), keep-alive is used within record-media-modal.vue
            type: Boolean,
            required: false, 
            default: false
        }
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