<script>

/*
 * To record some audio
 *
 */

import Vue from 'vue';
import 'video.js/dist/video-js.css';
import 'videojs-record/dist/css/videojs.record.css';
import videojs from 'video.js';
import 'webrtc-adapter';
import RecordRTC from 'recordrtc';
import Record from 'videojs-record/dist/videojs.record.js';
import VmsgEngine from 'videojs-record/dist/plugins/videojs.record.vmsg.js';
import { gsap } from 'gsap/all';
import VideoPlayer from '@schoolbox/videojs/video-player';
import RecordMediaModal from './record-media-modal';
import { getAudioOnlyRecordOptions, getVideoPlaybackOptions } from '@shared/videojs/videojsOptions';
import recordMediaEvents from '@shared/record-media/recordMediaEvents';


export default {

	components: {
		VideoPlayer,
		RecordMediaModal
	},

	data () {
		return {
			player: null, //the videojs-record player
			isDeviceReady: false, //to toggle the device display
			hasDeviceError: false, //can't access the requested device
			isRecording: false, //track recording status to toggle record/stop buttons
			isPlaybackActive: false, //need a different videojs player for playback to enable the progress controls
			isPlaybackVideoActive: false, //the playback video is available for control
			recordedCount: 0, //to show 'Record' or 'Re-record' on the button
			isPlaying: false, //to toggle play/pause controls
			isUploading: false, //to notify of update
			hasUploadError: false, //to notify of upload error, try again?
			hasUploaded: false, //notify UI of upload complete
		}
	},

	computed: {

		/*
		 * To know that you'll be re-recording a video, overwriting the previously recorded
		 *
		 * @returns { String }
		 */
		recordButtonText () {
			if (this.recordedCount == 0) {
				return 'Record';
			} else {
				return 'Re-Record';
			}
		},

		/*
		 * To know whether to show the convert button
		 *
		 * @returns { Boolean }
		 */
		showUploadButton () {
			if (this.recordedCount > 0 && !this.isRecording && !this.isUploading) {
				return true;
			} else {
				return false;
			}
		},

		/*
		 * To know whether to show the record button
		 *
		 * @returns { Boolean }
		 */
		showRecordButton () {
			if (this.isDeviceReady && !this.isRecording && !this.isUploading) {
				return true;
			} else {
				return false;
			}
		},

		/*
		 * To know whether to show the stop button
		 *
		 * @returns { Boolean }
		 */
		showStopButton () {
			if (this.isDeviceReady && this.isRecording && !this.isUploading) {
				return true;
			} else {
				return false;
			}
		},

	},

	mounted () {
		this.updateContainerHeight();
		this.hideForAnimateOn();
		this.initVideojs();
	},

	unmounted () {
		this.destroyVideojs();
	},

	methods: {

		/*
		 * To set up appropriate initial height on container, keeping the same size as a 4/3 video element
		 *
		 */
		updateContainerHeight () {
			const targetHeight = this.$refs.mediaContainer.getBoundingClientRect().width * 0.5625; //so there's no height jumps on switching videos
			this.$refs.mediaContainer.style.height = targetHeight + 'px';
		},

		/*
		 * to animate on when videojs ready
		 *
		 */
		hideForAnimateOn () {
			gsap.set(this.$refs.recordingVideoContainer, { alpha: 0 } );
			gsap.set(this.$refs.actions, { alpha: 0 } );
		},

		/*
		 * Start it up
		 *
		 */
		initVideojs () {
			this.player = videojs(this.$refs.recordingAudio, getAudioOnlyRecordOptions(), this.onVideoJsReady);
		},

		/*
		 * videojs is ready, so set up listeners and animate on
		 *
		 */
		onVideoJsReady () {
			try {
				this.animateOn();
				this.initVideojsRecordListeners();
				setTimeout(() => { //dodgy, trying to wait until 'VIDEOJS: wavesurfer.js microphone plugin enabled.' but can't see any event to track this...
					this.player.record().getDevice();
				}, 500);
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
			gsap.to(this.$refs.actions, 0.4, { alpha: 1, delay: 0.2 } );
		},

		/*
		 * track the events videojs-record offers to manage display
		 *
		 */
		initVideojsRecordListeners () {
			this.player.on('deviceReady', this.onDeviceReady);
			this.player.on('startRecord', this.onStartRecord);
			this.player.on('finishRecord', this.onFinishRecord);
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
			this.isRecording = true;
			this.canConvert = false; //disable the convert button
			this.isPlaybackActive = false;
			this.isPlaybackVideoActive = false;
		},

		/*
		 * onFinishRecord
		 *
		 */
		onFinishRecord () {
			this.$emit(recordMediaEvents.PROCCESS_STOPPED);
			this.recordedCount++;
			this.isRecording = false;
			this.isPlaybackActive = true;
			this.recordedData = this.player.recordedData; // the blob object contains the recorded data that we could convert
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
			this.hasDeviceError = true;
			console.error('device error:', this.player.deviceErrorCode);
		},

		/*
		 * enable the device, initially it's a blank screen until enabled
		 * may request permission on first use
		 *
		 */
		onEnableSelected () {
			this.player.record().getDevice();
		},
		


		/*
		 * enable the device, initially it's a blank screen until enabled
		 * may request permission on first use
		 *
		 */
		onStartRecordSelected () {
			this.player.record().start();
		},


		/*
		 * enable the device, initially it's a blank screen until enabled
		 * may request permission on first use
		 *
		 */
		onStopRecordSelected () {
			this.player.record().stop();
		},

		/*
		 * enable the device, initially it's a blank screen until enabled
		 * may request permission on first use
		 *
		 */
		async onUploadSelected () {
			const responseData = await this.uploadAudio();
			this.postUploadedMessage(responseData);
		},

		/*
		 * the mp3 is ready, so upload it up to SchoolBox
		 *
		 */
		async uploadAudio () {
			try {
				this.$emit(recordMediaEvents.PROCCESS_STARTED);
				this.isUploading = true;
				const formData = new FormData();
				formData.append('upload', this.recordedData, this.recordedData.name);
				const response = await axios.post('/storage/asyncUpload.php', formData);
				return response.data;
			} catch (error) {
				this.isUploading = false;
				this.hasUploadError = true;
				console.log(error);
			}
		},

		/*
		 * inform any listeners that a file was uploaded.
		 * put it in the dropzone, and inform the dropzone opener
		 * that a file was posted to it.
		 *
		 * @params {Object}
		 */
		postUploadedMessage (responseData) {
			const data = {
				title:    this.recordedData.name,
				mimeType: this.recordedData.type,
				filesize: this.recordedData.size,
				hash:     responseData.hash
			};
			this.isUploading = false;
			this.hasUploaded = true;
			setTimeout(() => { //put a slight delay so user sees the upload succesfull modal
				window.top.postMessage(JSON.stringify({ data: [data] }), location.origin);
				this.$emit(recordMediaEvents.PROCCESS_STOPPED);
			}, 1000);
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
		 * close the error modal
		 *
		 */
		onCloseErrorModalSelected () {
			this.$refs.errorModal.animateOff();
		},

		/*
		 * it's closed now, so disable the error to allow trying again
		 *
		 */
		onErrorModalClosed () {
			this.hasDeviceError = false;
		},

		/*
		 * Pass through the video options to play the latest recorded video
		 *
		 * @returns { Object }
		 */
		getVideoPlayerOptions () {
			return getVideoPlaybackOptions(this.recordedData);
		},

		/*
		 * to ensure the video-player.vue video is initiated before calling methods on it
		 *
		 */
		onPlaybackVideoReady () {
			this.isPlaybackVideoActive = true;
		}

	}

}
</script>

<template>
	<div>
		
		<div class="ab-pane">
			<div>
				<div class='media-container' ref="mediaContainer">
					
					<div ref='recordingVideoContainer'>
						<div class='audio-container'>
							<audio id="recordingAudio" ref="recordingAudio" class="video-js"></audio>
						</div><!-- no need for a audio-player, can just use video-player -->
						<div v-if="isPlaybackActive" class='video-container' key="playbackVideoContainer">
							<video-player
								ref="playbackVideo"
								:options="getVideoPlayerOptions()"
								@on-video-ready="onPlaybackVideoReady"
							/>
						</div>
					</div>

					<record-media-modal v-if="hasDeviceError" ref="errorModal" :closed-call-back="onErrorModalClosed" key="errorModal">
						<div class="alert-box warning">The camera appears to be already in use. Please close any Applications our Browser windows that may be using the camera and try again.</div>
						<button @click.prevent="onCloseErrorModalSelected">Close</button>
					</record-media-modal>
					<record-media-modal v-else-if="isUploading" ref="uploadingModal" key="uploadingModal">
						<div class="loader"></div>
						<p><em>Uploading audio, please wait...</em></p>
					</record-media-modal>
					<record-media-modal v-else-if="hasUploaded" ref="uploadedModal" key="uploadedModal">
						<p><em>Audio succesfully uploaded.</em></p>
					</record-media-modal>

				</div>
			</div>
		</div><!-- ab-pane -->

		<div class="component-action" ref="actions">
			<section>
				<a role="button" class="button" disabled></a><!-- just to keep the size between all record-x.vue consistent -->
				<a v-if="!isDeviceReady" @click.prevent="onEnableSelected" role="button" class="button" title="Enable Video" href="#turn-on-video">Turn on Audio</a>
				<transition name="fade">
					<a v-if="showUploadButton" @click.prevent="onUploadSelected" role="button" class="button"  title="Upload Audio" href="#upload-audio">Upload Audio</a>
				</transition>
				<transition name="fade" mode="out-in">
					<a v-if="showRecordButton" @click.prevent="onStartRecordSelected" role="button" class="button" title="Record Video" href="#record-audio">{{ recordButtonText }}</a>
					<a v-else-if="showStopButton" @click.prevent="onStopRecordSelected" role="button" class="button" title="Stop Video" href="#stop-recording-audio">Stop</a>
				</transition>
				<transition name="fade" mode="out-in">
					<a v-if="isPlaybackVideoActive && !$refs.playbackVideo.isPlaying" @click.prevent="$refs.playbackVideo.play()" role="button" class="button" title="Play Audio" href="#play-audio" key="play">Play</a>
					<a v-else-if="isPlaybackVideoActive && $refs.playbackVideo.isPlaying" @click.prevent="$refs.playbackVideo.pause()" role="button" class="button" title="Pause Audio" href="#pause-audio" key="pause">Pause</a>
				</transition>
			</section>
		</div>
		
	</div>
</template>

<style lang="scss" scoped>

.media-container {
	position: relative;
	width: 100%;
	height: 100%;
	background: black;
	overflow: hidden;
}

.audio-container {
	position: absolute;
	width: 100%;
	height: 100%;
	left: 0;
	top: 0;
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
