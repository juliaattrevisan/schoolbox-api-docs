<script>

/**
 * To record, playback and upload a screen recording into SB 
 *
 */

import 'video.js/dist/video-js.css';
import 'videojs-record/dist/css/videojs.record.css';
import videojs from 'video.js';
import 'webrtc-adapter';
import RecordRTC from 'recordrtc';
import Record from 'videojs-record/dist/videojs.record.js';
import TsEBMLEngine from 'videojs-record/dist/plugins/videojs.record.ts-ebml.js';
import RecordedMedia from '@schoolbox/videojs-record/recorded-media';
import OverMediaModal from '@schoolbox/videojs-record/over-media-modal';
import { getRecordScreenOptions } from '@shared/videojs/videojsOptions';
import recordMediaTypes from '@shared/record-media/recordMediaTypes';
import recordMediaEvents from '@shared/record-media/recordMediaEvents';
import { isOfMimeType } from '@shared/util/fileUtils';


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
        }
    },

    data () {
        return {
            player: null, //videojs-record instance,
            recordedData: null, //the recorded data that will be passed through to #recored-media
            isDeviceReady: false, //to toggle the device display
            hasPreRecordedData: false, //to display an existing recording on load
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            hasError: false, //to manage UI elements
            hasDeviceAlreadyInUseError: false, //can't access the requested device
            hasPermissionDeniedError: false, //didn't allow permission
            isRecordingOverMaxFileSize: false, //notify that the recording won't upload
            hasNewRecording: false, //need a different videojs player for playback to enable the progress controls
            isDeleteRecordingModalActive: false, //to confirm/deny whether to delete the video
        }
    },

	created () {
        this.recordMediaTypes = recordMediaTypes; //to be able to move between media types
        this.checkForPreRecordedData();
	},

    mounted () {
        if (!this.hasPreRecordedData) { //only if no pre recording available
            this.initVideojsRecord();
        }
    },

    beforeDestroy () {
        this.destroyVideojs();
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
         * Start up the videojs-record instance, which is just videojs with record functionality wrapped around it
         *
         */
        initVideojsRecord () {
            this.player = videojs(this.$refs.recordingVideo, getRecordScreenOptions(this.maxFileSize), this.onVideojsReady);
        },

        /**
         * videojs is ready, so set up listeners and animate on
         *
         */
        onVideojsReady () {
            this.initVideojsRecordRecordListeners();
            const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
            if (!isSafari) { //Safari requires a direct click on the request (for screen only, not video)
                this.player.record().getDevice(); //request the device to avoid needing to click the activate button
            }
        },

        /**
         * track the events videojs-record offers to manage display
         *
         */
        initVideojsRecordRecordListeners () {
            this.player.on('deviceReady', this.onDeviceReady);
            this.player.on('startRecord', this.onStartRecord);
            this.player.on('finishRecord', this.onFinishRecord);
            this.player.on('error', this.onError);
            this.player.on('deviceError', this.onDeviceError);
        },

        /**
         * onDeviceReady, dispatch to anyone listening, maybe the parent
         *
         */
        onDeviceReady () {
            this.isDeviceReady = true;
            const currentDeviceSettings = this.player.record().stream.getVideoTracks()[0].getSettings(); 
            this.$emit(recordMediaEvents.DEVICE_READY, currentDeviceSettings.width, currentDeviceSettings.height);
        },

        /**
         * onStartRecord
         *
         */
        onStartRecord () {
            document.querySelectorAll('.vjs-time-control').forEach((el) => el.style.backgroundColor = 'rgba(255, 0, 0, 0.25)');
        },

        /**
         * the recorded file is ready
         *
         */
        onFinishRecord () {
            if (this.isRecordingAboveMaxFileSize()) {
                this.onRecordingAboveMaxFileSize();
            } else {
                this.checkForConverting();
            }
        },

        /**
         * ensure the recording size is not above the max allowed
         *
         * @return { Boolean }
         */
        isRecordingAboveMaxFileSize () {
            if (this.player.recordedData.size > this.maxFileSize) {
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
            document.querySelectorAll('.vjs-time-control').forEach((el) => el.style.backgroundColor = 'transparent');
            this.$emit(recordMediaEvents.PROCCESS_STOPPED); //notify any UI to re-enable
            this.player.record().reset(); 
        },

        /**
         * video webm's are converted so they are seekable on playback, otherwise the video is ready to use
         *
         */
        checkForConverting () {
            if (this.player.recordedData.type && isOfMimeType(this.player.recordedData.type, 'video/webm')) { //if it's a webm
                this.player.on('finishConvert', this.onFinishConvert);
            } else {
                this.recordedVideoReady(this.player.recordedData);
            }
        },

        /**
         * the seekable webm file is ready
         *
         */
        onFinishConvert () {
            this.player.off('finishConvert', this.onFinishConvert);
            this.recordedVideoReady(this.player.convertedData);
        },

        /**
         * a media has been recorded, so can now either upload it, delete and re-record or play it back
         *
         */
        recordedVideoReady (mediaData) {
            document.querySelectorAll('.vjs-time-control').forEach((el) => el.style.backgroundColor = 'transparent');
            this.recordedData = mediaData; // the blob object contains the recorded data 
			this.hasNewRecording = true; //show the <player></player>
            this.$emit(recordMediaEvents.RECORDED_MEDIA_READY, this.recordedData); //notify anyone wishing to upload the media
        },

        /**
         * not sure what would cause this trigger yet, but it's in the videojs-record examples, probably an invalid src etc
         *
         */
        onError (element, error) {
            this.hasError = true;
            console.warn(error);
        },

        /**
         * onDeviceError - try and manage as many errors as possible
         *
         */
        onDeviceError () {
            this.hasError = true;
            const errorCode = String(this.player.deviceErrorCode);
            console.warn('errorCode = ' + errorCode);
            if (errorCode === 'NotReadableError: Could not start video source' || errorCode === 'AbortError: Starting video failed') {
                this.hasDeviceAlreadyInUseError = true;
            } else if (errorCode === 'NotAllowedError: Permission denied' || errorCode === 'NotAllowedError: The request is not allowed by the user agent or the platform in the current context.') {
                this.hasPermissionDeniedError = true;
            }
            console.warn(this.player.deviceErrorCode);
        },

        /**
         * ensure everything reset on possible modal re-display
         *
         */
        destroyVideojs () {
            if (this.player) {
                this.player.record().destroy();
            }
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
            this.hasPreRecordedData = false;  //set to false again for possible display or a re-recording
            this.hasNewRecording = false;
            if (this.player) { //relaunch the recorder if it's already set up
                this.player.record().getDevice(); 
            } else { //otherwise lets fire it up
                this.initVideojsRecord();
            }
        },

        /**
         * Ensure the recorded video ratio is respected
         *
         * @params { Number }
         * @params { Number }
         */
        onResize (width, height) {
            this.$emit(recordMediaEvents.RESIZE, width, height);
        },


    }

}
</script>

<template>
    
    <div class="container">
        
        <div class="media-container media-container-record" key="recordMediaContainer">
            <video ref="recordingVideo" playsinline class="video-js vjs-record-skin"></video>
        </div>

        <recorded-media 
            v-if="hasPreRecordedData || hasNewRecording"
            key="media-container-playback"
            class="media-container media-container-playback"
            :record-media-type="recordMediaTypes.SCREEN"
            :has-pre-recorded-data="hasPreRecordedData"
            :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
            :recorded-data="recordedData"
            @on-recorded-media-deleted="onRecordedMediaDeleted"
            @on-resize="onResize"
        />

        <over-media-modal v-if="hasError" :is-closing="isOverMediaModalClosing" :closed-call-back="onErrorModalClosed" key="errorModal">
            <div v-if="hasDeviceAlreadyInUseError" class="alert-box warning">Screen sharing appears to be already in use or permission has been denied. Please close any Applications or Browser windows that may be using the camera and try again.</div>
            <div v-else-if="hasPermissionDeniedError" class="alert-box alert">Permission to access the screen was denied. Please retry and grant access if you wish to proceed.</div>
            <div v-else-if="isRecordingOverMaxFileSize" class="alert-box alert">Sorry, the recording has stopped as it is over the allowed file size limit. Please record a shorter screen recording or request your administrator(s) to increase the allowed file size limit.</div>
            <div v-else class="alert-box alert">Sorry, an unknown error has occured.</div>
            <button @click.prevent="onCloseErrorModalSelected">Close</button>
        </over-media-modal>

    </div>

</template>

<style lang="scss" scoped>

@import '@schoolbox/videojs-record/videojs-record';

</style>
