<script>

/** 
 * To use the devices native tools to capture a CAMERA,
 * to easier allow toggling between device cameras
 *
 */

import RecordedMedia from '@schoolbox/videojs-record/recorded-media';
import OverMediaModal from '@schoolbox/videojs-record/over-media-modal';
import recordMediaTypes from '@shared/record-media/recordMediaTypes';
import recordMediaEvents from '@shared/record-media/recordMediaEvents';


export default {

    components: {
        RecordedMedia,
        OverMediaModal
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

    data () {
        return {
            hasPreRecordedData: false, //to display an existing recording on load
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            hasError: false, //to manage UI elements
            hasRecordPlayerError: false, //error with the record player
            isRecordingOverMaxFileSize: false, //notify that the recording won't upload
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
            return {
                accept: 'video/*',
                capture: 'user',
                attachPrompt: 'record video',
                iconClass: 'icon-video'
            }
        },


        /**
         * Listen for changes so can display the captured media
         *
         */
        initInputCaptureElement () {
            this.$refs.recorder.addEventListener('change', (event) => {
                this.onFileAdded(event.target.files[0]);
            });
        },

        /**
         * ensure the recording doesn't exceed the max file size otherwise allow upload
         *
         */ 
        onFileAdded (mediaData) {
            this.$refs.recorder.value = ''; //prevent the input capture element showing the preview
            if (this.isRecordingAboveMaxFileSize(mediaData)) {
                this.onRecordingAboveMaxFileSize();
            } else {
                this.addRecording(mediaData);
            }
        },

        /**
         * ensure the recording size is not above the max allowed
         *
         * @return { Boolean }
         */
        isRecordingAboveMaxFileSize (mediaData) {
            if (mediaData.size > this.maxFileSize) {
                return true;
            } else {
                return false;
            }
        },

        /**
         * display over limit error and reset for another possible recording
         *
         */
        onRecordingAboveMaxFileSize () {
            this.isRecordingOverMaxFileSize = true;
            this.hasError = true;
        },

        /**
         * add recording to be able to preview prior to uploading
         *
         */
        addRecording (mediaData) {
            this.recordedData = mediaData; // the blob object contains the recorded data 
            this.hasNewRecording = true; //show the <player></player>
            this.$emit(recordMediaEvents.RECORDED_MEDIA_READY, this.recordedData); //notify anyone wishing to upload the media
        },

        /**
         * close the error modal, animating out
         *
         */
        onCloseErrorModalSelected () {
            this.isOverMediaModalClosing = true;
        },

        /**
         * it's closed now, so disable the errors to allow trying again
         *
         */
        onErrorModalClosed () {
            this.hasError = false;
            this.isOverMediaModalClosing = false;
            this.hasDeviceAlreadyInUseError = false;
            this.hasPermissionDeniedError = false;
            this.isRecordingOverMaxFileSize = false;
        },

        /**
         * confirm deletion of the latest recording, setting up for another recording
         *
         */
        onRecordedMediaDeleted () {
            this.$emit(recordMediaEvents.RECORDED_MEDIA_DELETED);
            this.$refs.recorder.value = '';
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
                <input ref="recorder" class="dzone" type="file" :accept="captureOptions.accept" :capture="captureOptions.capture">
                <span :class="'file-input-note ' + captureOptions.iconClass">Tap here to {{ captureOptions.attachPrompt }}</span>
            </div>
        </div>
        
        <recorded-media 
            v-if="hasPreRecordedData || hasNewRecording"
            key="media-container-playback"
            class="media-container media-container-playback"
            :record-media-type="recordMediaTypes.CAMERA"
            :has-pre-recorded-data="hasPreRecordedData"
            :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
            :recorded-data="recordedData"
            @on-recorded-media-deleted="onRecordedMediaDeleted"
            @on-ratio-ready="onRatioReady"
        />

        <over-media-modal v-if="hasError" :is-closing="isOverMediaModalClosing" :closed-call-back="onErrorModalClosed" key="errorModal">
            <template v-if="isRecordingOverMaxFileSize">
                <div class="alert-box alert">Sorry, the recording is over the allowed file size limit. Please record a shorter video or request your administrator(s) to increase the allowed file size limit.</div>
                <button @click.prevent="onCloseErrorModalSelected">Close</button>
            </template>
            <template v-else>
                <div class="alert-box alert">Sorry, an unknown error has occured.</div>
                <button @click.prevent="onCloseErrorModalSelected">Close</button>
            </template>
        </over-media-modal>

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
