<script>

/**
 * To record audio only, input devices are unreliable, so is purely browser capture only
 *
 */

import RecordAudioOnlyBrowser from '@schoolbox/videojs-record/record-audio-only-browser';


export default {

    components: {
        RecordAudioOnlyBrowser
    },

    props: {
        preRecordedData: { //to display an already existing recording on startup
            type: Object,
            required: false,
        },
    },

}
</script>

<template>
        
    <record-audio-only-browser
        :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
        v-on="$listeners"
    />

</template>