<script>

/**
 * To take a screen recording on desktop, mobile not currently possible
 *
 */

import RecordScreenDevice from '@schoolbox/inputCapture/record-screen-device';
import RecordScreenBrowser from '@schoolbox/videojs-record/record-screen-browser';
import { hasDeviceCapture } from '@shared/util/featureDetectionUtils';


export default {

    components: {
        RecordScreenDevice,
        RecordScreenBrowser 
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
        
    <record-screen-device
        v-if="hasDeviceCapture"
        v-on="$listeners"
    />
    <record-screen-browser
        v-else
        :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
        v-on="$listeners"
    />

</template>