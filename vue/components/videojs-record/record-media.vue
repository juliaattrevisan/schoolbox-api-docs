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
            hasDeviceAlreadyInUseError: false, //can't access the requested device
            hasPermissionDeniedError: false, //didn't allow permission
            isPlayerActive: false, //need a different videojs player for playback to enable the progress controls
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
            if (this.isPlayerActive) {
                return true;
            } else {
                return false;
            }
        },

    },

	created () {
		this.recordMediaTypes = recordMediaTypes; //to be able to move between media types
	},

    mounted () {
        if (!this.isKeepAlive) {
            this.hideForAnimateOn();
            this.initVideojs();
        }
    },

    activated () {
        if (this.isKeepAlive) {
            this.hideForAnimateOn();
            this.initVideojs();
        }
    },

    unmounted () {
        this.destroyVideojs();
    },

    methods: {

        /**
         * to animate on when videojs ready
         *
         */
        hideForAnimateOn () {
            gsap.set(this.$refs.recordingMediaContainer, { alpha: 0 } );
        },

        /**
         * Start it up
         *
         */
        initVideojs () {
            this.player = videojs(this.$refs.recordingVideo, getRecordOptions(this.recordMediaType), this.onVideojsReady);
        },

        /**
         * videojs is ready, so set up listeners and animate on
         *
         */
        onVideojsReady () {
            this.animateOn();
            this.initVideojsRecordListeners();
            this.determinePlaybackStatus();
        },

        /**
         * an existing recording could have been passed through, so check whether to display it 
         *
         */
        determinePlaybackStatus () {
            setTimeout(() => { //dodgy, trying to wait until 'VIDEOJS: wavesurfer.js microphone plugin enabled.' but can't see any event to track this...
                if (this.preRecordedData) {
                    this.hasPreRecordedData = true; //to display pre recorded video
                    this.playbackData = {
                        src: this.getPlaybackDataSrc(),
                        type: this.preRecordedData.mime
                    } 
                    this.isPlayerActive = true;
                } else {
                    this.player.record().getDevice();
                }
            }, 200);
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
         * show the video
         *
         */
        animateOn () {
            gsap.to(this.$refs.recordingMediaContainer, 0.5, { alpha: 1 } );
        },

        /**
         * track the events videojs-record offers to manage display
         *
         */
        initVideojsRecordListeners () {

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
            this.$emit(recordMediaEvents.DEVICE_READY, this.player);
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
			this.isPlayerActive = true; //show the <player></player>
            this.$emit(recordMediaEvents.RECORDED_MEDIA_READY, this.recordedData); //notify anyone wishing to upload the media
        },

        /**
         * not sure what would cause this trigger yet, but it's in the videojs-record examples, probably an invalid src etc
         *
         */
        onError (element, error) {
            console.warn(error);
        },

        /**
         * onDeviceError - try and manage as many errors as possible
         *
         */
        onDeviceError () {
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
            this.isOverMediaModalClosing = false;
            this.hasDeviceAlreadyInUseError = false;
            this.hasPermissionDeniedError = false;
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
            this.isPlayerActive = false;
            this.player.record().getDevice(); //relaunch the recorder 
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

    }

}
</script>

<template>
    
    <div class="container">
        
        <div ref="recordingMediaContainer">
            <div class="media-container media-container-record">
                <video ref="recordingVideo" playsinline class="video-js"></video>
            </div>
            <div v-if="isPlayerActive" class="media-container media-container-playback" key="playbackMediaContainer">
                <audio-player
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
                />
            </div>
            <transition name="fade">
                <a v-if="showDeleteRecordingButton" @click.prevent="onDeleteRecordingSelected" class="delete-button" title="Delete &amp; Re-record" href="#delete-and-re-record" key="delete">Delete &amp; Re-record</a>
            </transition>
        </div><!-- recordingMediaContainer -->

        <over-media-modal v-if="hasDeviceAlreadyInUseError || hasPermissionDeniedError" :is-closing="isOverMediaModalClosing" :closed-call-back="onErrorModalClosed" key="errorModal">
            <template v-if="hasDeviceAlreadyInUseError">
                    <div class="alert-box warning">The camera appears to be already in use. Please close any Applications or Browser windows that may be using the camera and try again.</div>
                <button @click.prevent="onCloseErrorModalSelected">Close</button>
            </template>
            <template v-else-if="hasPermissionDeniedError">
                <div class="alert-box alert">Permission to access the device was denied. Please retry and grant access if you wish to proceed.</div>
                <button @click.prevent="onCloseErrorModalSelected">Close</button>
            </template>
        </over-media-modal>

        <over-media-modal v-else-if="isDeleteRecordingModalActive" :is-closing="isOverMediaModalClosing" :closed-call-back="onDeleteRecordingModalClosed" key="deleteRecordingModal">
            <div class="alert-box alert">Are you sure you wish to delete the current recording?</div>
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
    z-index: 1;
    left: 50%;
    top: 0;
    margin-bottom: 0;
    margin-left: -78px;
}

.modal-actions {
    display: flex;
    button {
        margin: 0px 2px; 
    }
}

.vjs-icon-record-start, .vjs-icon-record-stop {
    min-height: 0;
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s;
}

.fade-enter, .fade-leave-to /** .fade-leave-active below version 2.1.8 */ {
      opacity: 0;
}

</style>
