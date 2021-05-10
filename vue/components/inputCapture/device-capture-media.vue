<script>

/*
 * To use the devices native tools to capture a photo, video, or audio
 *
 */

import RecordedMedia from '@schoolbox/videojs-record/recorded-media';
import OverMediaModal from '@schoolbox/videojs-record/over-media-modal';
import recordMediaTypes from '@shared/record-media/recordMediaTypes';
import recordMediaEvents from '@shared/record-media/recordMediaEvents';
import { blobToDataURL } from '@shared/util/fileUtils';


export default {

    components: {
        RecordedMedia,
        OverMediaModal
    },

    props: {
        recordMediaType: { //currently should either be AUDIO_ONLY, CAMERA or SCREEN
            type: String,
            required: true,
            validator: function (value) {
                return [recordMediaTypes.AUDIO_ONLY, recordMediaTypes.CAMERA, recordMediaTypes.PHOTO].indexOf(value) !== -1 // The value must match one of these strings
            }
        },
        preRecordedData: { //to display an already existing recording on startup
            type: Object,
            required: false,
        },
    },

    data () {
        return {
            hasPreRecordedData: false, //to display an existing recording on load
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            hasError: false, //to manage UI elements
            hasRecordPlayerError: false, //error with the record player
            hasNewRecording: false, //need a different videojs player for playback to enable the progress controls
            isDeleteRecordingModalActive: false, //to confirm/deny whether to delete the video
            captureOptions: null //to distinguish between different media types
        }
    },

    created () {
        this.recordMediaTypes = recordMediaTypes; //to be able to move between media types
        this.checkForPreRecordedData();
        this.captureOptions = this.getCaptureOptions();
    },

    mounted () {
        this.initInputCaptureElement();
    },

    methods: {

        /**
         * Do we need to request the media device straight away? 
         * If not wait until it's requested - by deleting the pre recorded media recording
         * Otherwise we will just be displaying the existing pre recorded media
         *
         */
        checkForPreRecordedData () {
            if (this.preRecordedData) {
                this.hasPreRecordedData = true;
            }
        },

        /**
         * get the correct options depending on the selected media type
         *
         * @returns { Object }
         */
        getCaptureOptions () {
            if (this.recordMediaType === recordMediaTypes.PHOTO) {
                return {
                    accept: 'image/*',
                    capture: 'user',
                    attachPrompt: 'take a picture',
                    iconClass: 'icon-camera'
                }
            } else if (this.recordMediaType === recordMediaTypes.AUDIO_ONLY) {
                return {
                    accept: 'audio/*',
                    capture: 'microphone',
                    attachPrompt: 'record audio',
                    iconClass: 'icon-audio'
                }
            } else if (this.recordMediaType === recordMediaTypes.CAMERA) {
                return {
                    accept: 'video/*',
                    capture: 'user',
                    attachPrompt: 'record video',
                    iconClass: 'icon-video'
                }
            }
        },


        /**
         * Listen for changes so can display the captured media
         *
         */
        initInputCaptureElement () {
            const recorder = document.getElementById('recorder');
            recorder.addEventListener('change', (event) => {
                const file = event.target.files[0];
                this.onFileAdded(file);
            });
        },

        /**
         * get the correct options depending on the selected media type
         *
         * @returns { Blob }
         */
        async onFileAdded (mediaData) {
            this.recordedData = mediaData; // the blob object contains the recorded data 
            if (this.recordMediaType === recordMediaTypes.PHOTO) { //set up a .src in order to display the image
                this.recordedData.src = await blobToDataURL(this.recordedData);
            }
            this.hasNewRecording = true; //show the <player></player>
            this.$emit(recordMediaEvents.RECORDED_MEDIA_READY, this.recordedData); //notify anyone wishing to upload the media
        },

        /**
         * confirm deletion of the latest recording, setting up for another recording
         *
         */
        onRecordedMediaDeleted () {
            this.$emit(recordMediaEvents.RECORDED_MEDIA_DELETED);
            this.hasPreRecordedData = false;  //set to false again for possible display or a re-recording
            this.hasNewRecording = false;
        },

        /**
         * Ensure the recorded video ratio is respected
         *
         * @params { Number }
         * @params { Number }
         */
        onRatioReady (width, height) {
            this.$emit(recordMediaEvents.RATIO_READY, width, height);
        },

    }

}
</script>

<template>

    <div class="container">

        <div class="media-container media-container-record">
            <div class="file-input-container">
                <input class="dzone" type="file" :accept="captureOptions.accept" :capture="captureOptions.capture" id="recorder">
                <span :class="'file-input-note ' + captureOptions.iconClass">Tap here to {{ captureOptions.attachPrompt }}</span>
            </div>
        </div>
        
        <recorded-media 
            v-if="hasPreRecordedData || hasNewRecording"
            key="media-container-playback"
            class="media-container media-container-playback"
            :record-media-type="recordMediaType"
            :has-pre-recorded-data="hasPreRecordedData"
            :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
            :recorded-data="recordedData"
            @on-recorded-media-deleted="onRecordedMediaDeleted"
            @on-ratio-ready="onRatioReady"
        />

    </div>

</template>

<style lang="scss" scoped>

.container {
    position: absolute;
    width: 100%;
    height: 100%; //will be updated by the parent container, keeping it inline with the parent
    background: white;
    overflow: hidden;
}

.media-container {
    position: absolute;
    width: 100%;
    height: 100%;
    left: 0;
    top: 0;
}

    .media-container-record {
        z-index: 1;
    }

    .media-container-playback {
        z-index: 2;
    }

.file-input-container {
    position: absolute;
    width: 100%;
    height: 100%;
    text-align: center;
}    

.dzone::v-deep { //piggy back on the existing dropzone styles, and update as needed
    position: absolute;
    z-index: 1;
    width: 100%;
    height: 100%;
    font-size: 0px;
    text-indent: -999px; //hide the text, just leaving the dzone like border/display
}

.file-input-note {
    display: block;
    position: relative;
    z-index: 2;
    padding: 20px;
    font-size: 0.8rem;
    pointer-events: none;
}

</style>
