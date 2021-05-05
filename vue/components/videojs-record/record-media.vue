<script>

/*
 * To record, playback, convert and upload a camera/screen recording into SB
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
import VideoPlayer from '@schoolbox/videojs/video-player';
import OverMediaModal from '@schoolbox/videojs-record/over-media-modal';
import { getRecordOptions, getPlaybackOptions } from '@shared/videojs/videojsOptions';
import recordMediaTypes from '@shared/record-media/recordMediaTypes';
import recordMediaEvents from '@shared/record-media/recordMediaEvents';


export default {

	components: {
		OverMediaModal,
		VideoPlayer
	},

	props: {
		recordMediaType: { //currently should either be AUDIO_ONLY, CAMERA or SCREEN
			type: String,
	  		required: true,
	  		 validator: function (value) {
				return [recordMediaTypes.AUDIO_ONLY, recordMediaTypes.CAMERA, recordMediaTypes.SCREEN].indexOf(value) !== -1 // The value must match one of these strings
			}
		}
	},

	data () {
		return {
			player: null, //videojs-record instance
			isDeviceReady: false, //to toggle the device display
			isOverMediaModalClosing: false, //to animate out the over-media-modal
			hasDeviceAlreadyInUseError: false, //can't access the requested device
			hasPermissionDeniedError: false, //didn't allow permission
			targetHeight: '0px', //to set the height based on the aspect ratio of the video
			isPlaybackActive: false, //need a different videojs player for playback to enable the progress controls
			isDeleteRecordingModalActive: false, //to confirm/deny whether to delete the video
		}
	},

	computed: {

		/*
		 * To know whether to show the delete recording button
		 *
		 * @returns { Boolean }
		 */
		showDeleteRecordingButton () {
			if (this.isPlaybackActive) {
				return true;
			} else {
				return false;
			}
		},

	},

	activated () {
		this.updateContainerHeight();
		this.hideForAnimateOn();
		this.initVideojs();
	},

	unmounted () {
		this.destroyVideojs();
	},

	methods: {

		/*
		 * To set up appropriate initial height on container, keeping the same size as a 16:9 video element
		 *
		 */
		updateContainerHeight () {
			this.targetHeight = (this.$refs.container.getBoundingClientRect().width * 0.5625) + 'px'; //so there's no height jumps on switching videos
		},

		/*
		 * to animate on when videojs ready
		 *
		 */
		hideForAnimateOn () {
			gsap.set(this.$refs.recordingVideoContainer, { alpha: 0 } );
		},

		/*
		 * Start it up
		 *
		 */
		initVideojs () {
			this.player = videojs(this.$refs.recordingVideo, getRecordOptions(this.recordMediaType), this.onVideojsReady);
		},

		/*
		 * videojs is ready, so set up listeners and animate on
		 *
		 */
		onVideojsReady () {
			try {
				this.animateOn();
				this.initVideojsRecordListeners();
				if (this.recordMediaType === recordMediaTypes.AUDIO_ONLY) { //to mp3 is ready
					setTimeout(() => { //dodgy, trying to wait until 'VIDEOJS: wavesurfer.js microphone plugin enabled.' but can't see any event to track this...
						this.player.record().getDevice();
					}, 500);
				} else {
					this.player.record().getDevice();
				}
			} catch (error) {
				console.log(error);
			}
		},

		/*
		 * show the video and controls
		 *
		 */
		animateOn () {
			gsap.to(this.$refs.recordingVideoContainer, 0.5, { alpha: 1 } );
		},

		/*
		 * track the events videojs-record offers to manage display
		 *
		 */
		initVideojsRecordListeners () {

			this.player.on('deviceReady', this.onDeviceReady);
			this.player.on('startRecord', this.onStartRecord);

			const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
			if (this.recordMediaType === recordMediaTypes.AUDIO_ONLY) { //to mp3 is ready
				this.player.on('finishRecord', this.onFinishRecord);
			} else if (isSafari) { //safari uses mp4 to record, while all others use default webm (with conversion to enable seekable webm)
				this.player.on('finishRecord', this.onFinishRecord);
			} else {
				this.player.on('finishConvert', this.onFinishConvert);
			}

			this.player.on('error', this.onError);
			this.player.on('deviceError', this.onDeviceError);

		},

		/*
		 * onDeviceReady
		 *
		 */
		onDeviceReady () {
			this.isDeviceReady = true;
		},

		/*
		 * onStartRecord
		 *
		 */
		onStartRecord () {
			this.$emit(recordMediaEvents.PROCCESS_STARTED);
		},

		/*
		 * the record mp3 or mp4 (Safari) is ready
		 *
		 */
		onFinishRecord () {
			this.recordedVideoReady(this.player.recordedData);
		},

		/*
		 * the seekable webm file is ready
		 *
		 */
		onFinishConvert () {
			this.recordedVideoReady(this.player.convertedData);
		},

		/*
		 * a video has been recorded, so can now either upload it, delete and re-record or play it back
		 *
		 */
		recordedVideoReady (videoData) {
			this.$emit(recordMediaEvents.PROCCESS_STOPPED);
			this.recordedData = videoData; // the blob object contains the recorded data
			this.isPlaybackActive = true; //show the <video-player></video-player>
			this.$emit(recordMediaEvents.RECORDED_MEDIA_READY, this.recordedData); //notify anyone wishing to upload the media
		},

		/*
		 * not sure what would cause this trigger yet, but it's in the videojs-record examples, probably an invalid src etc
		 *
		 */
		onError (element, error) {
			console.warn(error);
		},

		/*
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

		/*
		 * ensure everything reset on possible modal re-display
		 *
		 */
		destroyVideojs () {
			if (this.player) {
				this.player.dispose();
			}
		},

		/*
		 * close the error modal, animating out
		 *
		 */
		onCloseErrorModalSelected () {
			this.isOverMediaModalClosing = true;
		},

		/*
		 * it's closed now, so disable the errors to allow trying again
		 *
		 */
		onErrorModalClosed () {
			this.isOverMediaModalClosing = false;
			this.hasDeviceAlreadyInUseError = false;
			this.hasPermissionDeniedError = false;
		},

		/*
		 * to be able to record another video
		 *
		 */
		onDeleteRecordingSelected () {
			this.isDeleteRecordingModalActive = true;
		},

		/*
		 * confirm deletion of the latest recording, setting up for another recording
		 *
		 */
		onConfirmDeleteRecordingSelected () {
			this.$emit(recordMediaEvents.RECORDED_MEDIA_DELETED);
			this.isDeleteRecordingModalActive = false;
			this.isPlaybackActive = false;
			this.player.record().getDevice(); //relaunch the recorder
		},

		/*
		 * close the delete recording modal
		 *
		 */
		onCancelDeleteRecordingSelected () {
			this.isOverMediaModalClosing = true;
		},

		/*
		 * closed the delete recording modal, cancel has been selected rather than confirm
		 *
		 */
		onDeleteRecordingModalClosed () {
			this.isOverMediaModalClosing = false;
			this.isDeleteRecordingModalActive = false;
		},

		/*
		 * Pass through the video options to play the latest recorded video
		 *
		 * @returns { Object }
		 */
		getVideoPlayerOptions () {
			return getPlaybackOptions(this.recordedData);
		},

	}

}
</script>

<template>

	<div class="container" ref="container" :style="{ height: targetHeight }">

		<div ref="recordingVideoContainer">
			<div class="video-container">
				<video ref="recordingVideo" playsinline class="video-js"></video>
			</div>
			<div v-if="isPlaybackActive" class="video-container" key="playbackVideoContainer">
				<video-player
					ref="playbackVideo"
					:options="getVideoPlayerOptions()"
				/>
			</div>
			<transition name="fade">
				<a v-if="showDeleteRecordingButton" @click.prevent="onDeleteRecordingSelected" class="button delete-button" title="Delete &amp; Re-record" href="#delete-and-re-record" key="delete">Delete &amp; Re-record</a>
			</transition>
		</div><!-- recordingVideoContainer -->

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
			<div><p>Are you sure you wish to delete the current recording?</p></div>
			<div class="modal-actions">
				<button @click.prevent="onConfirmDeleteRecordingSelected" class="submit">Delete</button>
				<button @click.prevent="onCancelDeleteRecordingSelected">Cancel</button>
			</div>
		</over-media-modal>

	</div>

</template>

<style lang="scss" scoped>

.container {
	position: absolute;
	width: 100%;
	height: 300px; //will be updated by parent
	background: black;
	overflow: hidden;
	h2, h3 {
		max-width: none;
	}
}

.video-container {
	position: absolute;
	width: 100%;
	height: 100%;
	left: 0;
	top: 0;
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

.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
  	opacity: 0;
}

</style>
