<script>

/*
 * To record some media
 *
 */

import RecordVideo from './record-video';
import RecordAudio from './record-audio';
import mediaTypes from '../js/mediaTypes';

export default {
    
    components: {
        RecordVideo,
        RecordAudio
    },

    data () {
		return {
            currentMediaType: '' //the currently selected media recording
        }
    },

    created () {
        this.initConsts();
    },

    methods: {

        /*
         * For consts to be available within the template
         * 
         */ 
        initConsts () {
            this.MEDIA_TYPE_VIDEO = mediaTypes.VIDEO;
            this.MEDIA_TYPE_AUDIO = mediaTypes.AUDIO;
        },

        /*
         * To toggle between the different media types
         * 
         * @params {String}
         * @returns
         */ 
        onMediaSelected (mediaType) {
            this.currentMediaType = mediaType;
        }

    }

}
</script>

<template>
    <div>
        <div class="ab-pane">
            <div>
                <button @click="onMediaSelected(MEDIA_TYPE_VIDEO)">Video</button>
                <button @click="onMediaSelected(MEDIA_TYPE_AUDIO)">Audio</button>
            </div>
            <div>
                <record-video v-if="currentMediaType === MEDIA_TYPE_VIDEO" />
                <record-audio v-else-if="currentMediaType === MEDIA_TYPE_AUDIO" />
            </div>
        </div>
        <div class="component-action recorderButtons">
            <section>
                <a role="button" class="button record" data-interaction="audio-record-start" href="#">Start Recording</a>
                <!-- <a role="button" class="button recording hide" data-interaction="audio-record-recording" disabled href="#">Recording...</a>
                <a role="button" class="button disabled" data-interaction="audio-record-disabled" disabled href="#">No recording devices available</a>
                <a role="button" class="button play hide" data-interaction="audio-record-play" href="#">Play</a>
                <a role="button" class="button stop hide" data-interaction="audio-record-stop" href="#">Stop</a>
                <a role="button" class="button upload hide" data-interaction="audio-record-upload" href="#">Upload Audio</a>
                <a role="button" class="button uploading hide" data-interaction="audio-record-uploading" disabled href="#">Uploading...</a>
                <a role="button" class="button cancel" href="#">Cancel</a> -->
            </section>
        </div>
    </div>
</template>

<style lang="scss" scoped>
    .box {
        width: 100px;
        height: 100px;
        margin: 50px;
        background: red;
    }
</style>