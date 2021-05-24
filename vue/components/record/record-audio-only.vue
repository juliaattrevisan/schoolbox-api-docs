<script>

/**
 * To record audio only, input devices are unreliable, so is purely browser capture only
 *
 */

// import RecordPhotoDevice from '@schoolbox/inputCapture/record-photo-device';
import RecordAudioOnlyBrowser from '@schoolbox/videojs-record/record-audio-only-browser';
// import { hasDeviceCapture } from '@shared/util/featureDetectionUtils';


export default {

    components: {
        RecordAudioOnlyBrowser
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

    // created () { //these vars are created so they can be accessed within the template
    //     this.hasDeviceCapture = hasDeviceCapture();
    // },

}
</script>

<template>
        
    <record-audio-only-browser
        :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
        v-on="$listeners"
    />

</template>