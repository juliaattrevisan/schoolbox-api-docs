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
import AudioOnlyPlayer from '@schoolbox/videojs/audio-only-player';
import PhotoPlayer from '@schoolbox/videojs/photo-player';
import VideoPlayer from '@schoolbox/videojs/video-player';
import OverMediaModal from '@schoolbox/videojs-record/over-media-modal';
import { getRecordOptions, getVideoPlayerOptionsWithBlob, getVideoPlayerOptionsWithSrc, getAudioPlayerOptionsWithBlob, getAudioPlayerOptionsWithSrc } from '@shared/videojs/videojsOptions';
import recordMediaTypes from '@shared/record-media/recordMediaTypes';
import recordMediaEvents from '@shared/record-media/recordMediaEvents';


export default {

    components: {
        OverMediaModal,
        AudioOnlyPlayer,
        PhotoPlayer,
        VideoPlayer
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
            player: null, //videojs-record instance
            isDeviceReady: false, //to toggle the device display
            hasPreRecordedData: false, //to display an existing recording on load
            isOverMediaModalClosing: false, //to animate out the over-media-modal
            hasError: false, //to manage UI elements
            hasDeviceAlreadyInUseError: false, //can't access the requested device
            hasPermissionDeniedError: false, //didn't allow permission
            hasRecordPlayerError: false, //error with the record player
            hasNewRecording: false, //need a different videojs player for playback to enable the progress controls
            isDeleteRecordingModalActive: false, //to confirm/deny whether to delete the video
        }
    },

    computed: {

        /**
         * To know whether to show the delete recording button
         *
         * @returns { Boolean }
         */
        showDeleteRecordingButton () {
            if ((this.hasPreRecordedData || this.hasNewRecording) && !this.hasError) {
                return true;
            } else {
                return false;
            }
        },

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
                this.playbackData = {
                    src: this.getPlaybackDataSrc(),
                    type: this.preRecordedData.mime
                } 
            }
        },

        /**
         * to know where to reference the saved file from
         *
         * @returns { String } 
         */
        getPlaybackDataSrc () {
            if (this.recordMediaType === recordMediaTypes.PHOTO) {
                return `/storage/image.php?hash=${this.preRecordedData.hash}`;
            } else {
                return `/cms/sendVideo.php?id=${this.preRecordedData.hash}&type=storage`;
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

            const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent); //safari uses mp4 to record, while all others use default webm (with conversion to enable seekable webm)
            if (isSafari || this.recordMediaType === recordMediaTypes.AUDIO_ONLY || this.recordMediaType === recordMediaTypes.PHOTO) { //the mp3 (AUDIO_ONLY) or png (PHOTO) is ready
                this.player.on('finishRecord', this.onFinishRecord);
            } else {
                this.player.on('finishConvert', this.onFinishConvert);
            }

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
            this.$emit(recordMediaEvents.PROCCESS_STARTED);
        },

        /**
         * the record mp3 or mp4 (Safari) is ready
         *
         */
        onFinishRecord () {
            this.recordedVideoReady(this.player.recordedData);
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
        recordedVideoReady (mediaData) {
            this.$emit(recordMediaEvents.PROCCESS_STOPPED);
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
            this.hasRecordPlayerError = false;
        },

        /**
         * to be able to record another video
         *
         */
        onDeleteRecordingSelected () {
            this.isDeleteRecordingModalActive = true;
        },

        /**
         * confirm deletion of the latest recording, setting up for another recording
         *
         */
        onConfirmDeleteRecordingSelected () {
            this.$emit(recordMediaEvents.RECORDED_MEDIA_DELETED);
            this.isDeleteRecordingModalActive = false;
            this.hasPreRecordedData = false;  //set to false again for possible display or a re-recording
            this.hasNewRecording = false;
            if (this.player) { //relaunch the recorder if it's already set up
                this.player.record().getDevice(); 
                console.warn('relaunch');
            } else { //otherwise lets fire it up
                this.initVideojsRecord();
            }
        },

        /**
         * close the delete recording modal
         *
         */
        onCancelDeleteRecordingSelected () {
            this.isOverMediaModalClosing = true;
        },

        /**
         * closed the delete recording modal, cancel has been selected rather than confirm
         *
         */
        onDeleteRecordingModalClosed () {
            this.isOverMediaModalClosing = false;
            this.isDeleteRecordingModalActive = false;
        },

        /**
         * Pass through the audio options to play the latest recorded video
         *
         * @returns { Object }
         */
        getAudioPlayerOptions () {
            if (this.hasPreRecordedData) {
                return getAudioPlayerOptionsWithSrc(this.playbackData);
            } else {
                return getAudioPlayerOptionsWithBlob(this.recordedData);
            }
        },

        /**
         * Pass through the audio options to play the latest recorded video
         *
         * @returns { String }
         */
        getImgSrc () {
            if (this.hasPreRecordedData) { //otherwise its the already saved image src
                return this.playbackData.src;
            } else { //is already a Data URL that can be passed to <image-player></image-player>
                return this.recordedData; 
            }
        },

        /**
         * Pass through the video options to play the latest recorded video
         *
         * @returns { Object }
         */
        getVideoPlayerOptions () {
            if (this.hasPreRecordedData) {
                return getVideoPlayerOptionsWithSrc(this.playbackData);
            } else {
                return getVideoPlayerOptionsWithBlob(this.recordedData);
            }
        },

        /**
         * Just pass through to the parent so they can ensure the container's height is kept up to date
         *
         * @params { HTMLVideoElement }
         */
        onRecordPlayerLoaded (htmlVideoElement) {
            this.$emit(recordMediaEvents.RATIO_READY, htmlVideoElement);
        },

        /**
         * A record player has got an error
         *
         */
        onRecordPlayerError () {
            this.hasRecordPlayerError = true;
            this.hasError = true;
        }

    }

}
</script>

<template>
    
    <div class="container">
        
        <div ref="recordingMediaContainer">
            <div class="media-container media-container-record" key="recordMediaContainer">
                <video ref="recordingVideo" playsinline class="video-js vjs-record-skin"></video>
            </div>
            <div v-if="hasPreRecordedData || hasNewRecording" class="media-container media-container-playback" key="playbackMediaContainer">
                <audio-only-player
                    v-if="recordMediaType === recordMediaTypes.AUDIO_ONLY"
                    :options="getAudioPlayerOptions()"
                />
                <photo-player
                    v-else-if="recordMediaType === recordMediaTypes.PHOTO"
                    :img-src="getImgSrc()"
                    :is-new-photo="hasPreRecordedData ? false : true"
                />
                <video-player
                    v-else
                    :options="getVideoPlayerOptions()"
                    @on-video-data-loaded="onRecordPlayerLoaded"
                    @on-video-error="onRecordPlayerError"
                />
            </div>
            <transition name="fade">
                <a v-if="showDeleteRecordingButton" @click.prevent="onDeleteRecordingSelected" class="button delete-button" title="Delete" href="#delete" key="delete">Delete</a>
            </transition>
        </div><!-- recordingMediaContainer -->

        <over-media-modal v-if="hasError" :is-closing="isOverMediaModalClosing" :closed-call-back="onErrorModalClosed" key="errorModal">
            <template v-if="hasDeviceAlreadyInUseError">
                    <div class="alert-box warning">The camera appears to be already in use. Please close any Applications or Browser windows that may be using the camera and try again.</div>
                <button @click.prevent="onCloseErrorModalSelected">Close</button>
            </template>
            <template v-else-if="hasPermissionDeniedError">
                <div class="alert-box alert">Permission to access the device was denied. Please retry and grant access if you wish to proceed.</div>
                <button @click.prevent="onCloseErrorModalSelected">Close</button>
            </template>
            <template v-else-if="hasRecordPlayerError">
                <div class="alert-box alert">Sorry, the recorded media cannot be accessed.</div>
            </template>
        </over-media-modal>

        <over-media-modal v-else-if="isDeleteRecordingModalActive" :is-closing="isOverMediaModalClosing" :closed-call-back="onDeleteRecordingModalClosed" key="deleteRecordingModal">
            <p>Are you sure you wish to delete the current recording?</p>
            <div class="modal-actions">
                <button @click.prevent="onConfirmDeleteRecordingSelected">Confirm</button>
                <button @click.prevent="onCancelDeleteRecordingSelected">Cancel</button>
            </div>
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

.delete-button {
    position: absolute;
    z-index: 3;
    right: 10px;
    top: 10px;
}

.modal-actions {
    display: flex;
    button {
        margin: 0px 2px; 
    }
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s;
}

.fade-enter, .fade-leave-to /** .fade-leave-active below version 2.1.8 */ {
    opacity: 0;
}

.vjs-record-skin::v-deep {
    .vjs-device-button.vjs-control { //not in the centre of the frame, something from SB css is probably getting in the way
        top: 42%;
        margin-left: -2.6em;
    }
    .vjs-control-bar { //fatten up the bottom control panel
        height: 5em;
        padding: 0px 10px;
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
        transition: color 0.2s;
        &:hover {
            color: rgb(250, 54, 93);
        }
    }
    .vjs-icon-photo-camera {
        transform: scale(2.3);
        color: #fff;
    }
    .vjs-control:focus::before, .vjs-control:hover::before, .vjs-control:focus {
        text-shadow: none;
    }
    .vjs-time-control  {
        width: auto;
        min-width: 0;
        padding: 0px 2px;
        font-size: 1.1rem;
    }
    .vjs-button > .vjs-icon-placeholder::before {
        color: white;
        font-size: 4em;
        line-height: 1.25;
    }
    .vjs-control.vjs-record-indicator,
    .vjs-control.vjs-record-indicator:before,
    .vjs-control.vjs-record-indicator:after {
        transform: scale(1.5);
    }
    .vjs-control.vjs-record-indicator:before {
        left: -1.9rem;
    }
    @media screen and (max-width: 640px) {
        .vjs-time-control  {
            font-size: 0.9rem;
            line-height: 3.3rem;
        }
    }
}


</style>
