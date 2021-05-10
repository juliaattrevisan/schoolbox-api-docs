<script>

/**
 * To record, playback and upload a camera/screen recording into SB 
 * It currently always sits within a parent, currently #evidence-media-recording and #record-medida-modal
 *
 */

import 'video.js/dist/video-js.css';
import 'videojs-record/dist/css/videojs.record.css';
import videojs from 'video.js';
import 'webrtc-adapter';
import RecordRTC from 'recordrtc';
import Record from 'videojs-record/dist/videojs.record.js';
import VmsgEngine from 'videojs-record/dist/plugins/videojs.record.vmsg.js';
import TsEBMLEngine from 'videojs-record/dist/plugins/videojs.record.ts-ebml.js';
import { gsap } from 'gsap/all';
import RecordedMedia from '@schoolbox/videojs-record/recorded-media';
import OverMediaModal from '@schoolbox/videojs-record/over-media-modal';
import { getRecordOptions } from '@shared/videojs/videojsOptions';
import recordMediaTypes from '@shared/record-media/recordMediaTypes';
import recordMediaEvents from '@shared/record-media/recordMediaEvents';
import { isOfFileType, dataURLtoBlob } from '@shared/util/fileUtils';


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
                return [recordMediaTypes.AUDIO_ONLY, recordMediaTypes.CAMERA, recordMediaTypes.SCREEN, recordMediaTypes.PHOTO].indexOf(value) !== -1 // The value must match one of these strings
            }
        },
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
            hasNewRecording: false, //need a different videojs player for playback to enable the progress controls
            isDeleteRecordingModalActive: false, //to confirm/deny whether to delete the video
        }
    },

	created () {
        this.recordMediaTypes = recordMediaTypes; //to be able to move between media types
        this.checkForPreRecordedData();
	},

    mounted () {
        if (!this.isKeepAlive && !this.hasPreRecordedData) { //only if no pre recording available
            this.initVideojsRecord();
        }
    },

    activated () {
        if (this.isKeepAlive && !this.hasPreRecordedData) { //only if no pre recording available
            this.initVideojsRecord();
        }
    },

    unmounted () {
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
            this.player = videojs(this.$refs.recordingVideo, getRecordOptions(this.recordMediaType), this.onVideojsReady);
        },

        /**
         * videojs is ready, so set up listeners and animate on
         *
         */
        onVideojsReady () {
            this.initVideojsRecordRecordListeners();
            setTimeout(() => { //dodgy, trying to wait until 'VIDEOJS: wavesurfer.js microphone plugin enabled.' but can't see any event to track this...
                this.player.record().getDevice(); //request the device to avoid needing to click the activate button
            }, 200);    
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
            this.$emit(recordMediaEvents.DEVICE_READY, this.player.record().stream.getVideoTracks()[0]);
        },

        /**
         * onStartRecord
         *
         */
        onStartRecord () {
            document.querySelectorAll('.vjs-time-control').forEach((el) => el.style.backgroundColor = 'rgba(255, 0, 0, 0.25)');
            this.$emit(recordMediaEvents.PROCCESS_STARTED);
        },

        /**
         * the recorded file is ready
         *
         */
        onFinishRecord () {
            if (this.player.recordedData.name && isOfFileType(this.player.recordedData.name, 'webm')) { //webm's are converted so they are seekable on playback
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
            this.recordedVideoReady(this.player.convertedData);
        },

        /**
         * a media has been recorded, so can now either upload it, delete and re-record or play it back
         *
         */
        async recordedVideoReady (mediaData) {
            document.querySelectorAll('.vjs-time-control').forEach((el) => el.style.backgroundColor = 'transparent');
            this.$emit(recordMediaEvents.PROCCESS_STOPPED); //notify any UI to re-enable
            if (this.recordMediaType === recordMediaTypes.PHOTO) { //the recorded photo is just a DataURL, so need to set up a Blob
                this.recordedData = await dataURLtoBlob(mediaData); 
                this.recordedData.name = 'photo.jpeg'
                this.recordedData.src = mediaData; 
            } else {
                this.recordedData = mediaData; // the blob object contains the recorded data 
            }
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
            if (errorCode === 'NotReadableError: Could not start video source' || 'AbortError: Starting video failed') {
                this.hasDeviceAlreadyInUseError = true;
            } else if (errorCode === 'NotAllowedError: Permission denied' || 'NotAllowedError: The request is not allowed by the user agent or the platform in the current context.') {
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
                this.player.dispose();
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
        onRatioReady (width, height) {
            this.$emit(recordMediaEvents.RATIO_READY, width, height);
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
            :record-media-type="recordMediaType"
            :has-pre-recorded-data="hasPreRecordedData"
            :pre-recorded-data="preRecordedData ? preRecordedData : undefined"
            :recorded-data="recordedData"
            @on-recorded-media-deleted="onRecordedMediaDeleted"
            @on-ratio-ready="onRatioReady"
        />

        <over-media-modal v-if="hasError" :is-closing="isOverMediaModalClosing" :closed-call-back="onErrorModalClosed" key="errorModal">
            <template v-if="hasDeviceAlreadyInUseError">
                    <div class="alert-box warning">The camera appears to be already in use. Please close any Applications or Browser windows that may be using the camera and try again.</div>
                <button @click.prevent="onCloseErrorModalSelected">Close</button>
            </template>
            <template v-else-if="hasPermissionDeniedError">
                <div class="alert-box alert">Permission to access the device was denied. Please retry and grant access if you wish to proceed.</div>
                <button @click.prevent="onCloseErrorModalSelected">Close</button>
            </template>
        </over-media-modal>

    </div>

</template>

<style lang="scss" scoped>

.container {
    position: absolute;
    width: 100%;
    height: 100%; //will be updated by the parent container, keeping it inline with the video dimensions
    background: black;
    overflow: hidden;
    h2, h3 {
        max-width: none;
    }
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

.vjs-record-skin::v-deep {
    .vjs-device-button.vjs-control { //not in the centre of the frame, something from SB css is probably getting in the way
        top: 42%;
        margin-left: -2.6em;
    }
    .vjs-control-bar { //fatten up the bottom control panel
        height: 5em;
        padding: 0px;
    }
    .vjs-control {
        width: 5em;
    }
    .vjs-icon-record-start,
    .vjs-icon-record-stop,
    .vjs-icon-photo-camera { //make the start/stop record buttons big and fat and put in the middle
        position: absolute;
        z-index: 2;
        left: 50%;
        margin-left: -20px;
        bottom: 0px; 
        transform-origin: center;
        transform: scale(3.1);
        color: #f03;
        transition: color 0.2s, transform 0.2s;
        &:hover {
            transform: scale(2.8);
        }
    }
    .vjs-icon-photo-camera { //shrink a bit, bit big compared to the other icons
        transform: scale(2.3);
        color: #fff;
        &:hover {
            transform: scale(2.1);
        }
    }
    .vjs-control:focus::before, .vjs-control:hover::before, .vjs-control:focus {
        text-shadow: none;
    }
    .vjs-time-control { //make the time stand out a bit more
        width: auto;
        min-width: 0;
        padding: 0px 2px;
        font-size: 1.1rem;
        transition: background-color 0.3s;
    }
    .vjs-current-time { //and pull the time of from the side
        padding-left: 10px;
    }
    .vjs-duration {
        padding-right: 10px;
    }
    .vjs-fullscreen-control { //currently if the user re-records in full screen, this will not be reflected, so prevent for now
        display: none;
    }
    .vjs-button > .vjs-icon-placeholder::before { //make all the buttons bigger
        color: white;
        font-size: 4em;
        line-height: 1.25;
    }
    .vjs-play-control { //it'll never be shown, as once recored moves to another player
        display: none;
    }
    .vjs-control.vjs-record-indicator { //move the record note further down
        top: 20px;
    }
    .vjs-control.vjs-record-indicator,
    .vjs-control.vjs-record-indicator:before,
    .vjs-control.vjs-record-indicator:after { //and make the record note stand out more
        transform: scale(1.8);
    }
    .vjs-control.vjs-record-indicator:before { //provide a bit more padding to the recording dot
        left: -1.8rem;
    }
    @media screen and (max-width: 640px) { //shrink down a bit
        .vjs-time-control  {
            font-size: 0.9rem;
            line-height: 3.3rem;
        }
    }
}

</style>
