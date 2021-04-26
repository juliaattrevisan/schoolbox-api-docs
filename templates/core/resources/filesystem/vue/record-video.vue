<script>

/*
 * To record, playback, convert and upload a video into SB 
 *
 */

import 'video.js/dist/video-js.css';
import 'videojs-record/dist/css/videojs.record.css';
import videojs from 'video.js';
import 'webrtc-adapter';
import RecordRTC from 'recordrtc';
import Record from 'videojs-record/dist/videojs.record.js';
import TsEBMLEngine from 'videojs-record/dist/plugins/videojs.record.ts-ebml.js';
import axios from 'axios';
import { gsap } from 'gsap/all';
import RecordMediaModal from './record-media-modal';
import VideoPlayer from '@schoolbox/videojs/video-player';
import { getRecordCameraOptions, getRecordScreenOptions, getPlaybackOptions } from '@shared/videojs/videojsOptions';
import { convertFilenameExtension } from '@shared/util/fileUtils';
import { convertToMp4 } from '@shared/video/convertVideo';
import mediaTypes from '../js/mediaTypes';
import recordMediaEvents from '../js/recordMediaEvents';


export default {

	components: {
		RecordMediaModal,
		VideoPlayer
	},

	props: {
		mediaType: { //currently should either be CAMERA or SCREEN
			type: String,
	  		required: true,
	  		 validator: function (value) {
				return [mediaTypes.CAMERA, mediaTypes.SCREEN].indexOf(value) !== -1 // The value must match one of these strings
			}
		}
	},

	data () {
		return {
			isDeviceReady: false, //to toggle the device display
			hasDeviceAlreadyInUseError: false, //can't access the requested device
			hasPermissionDeniedError: false, //didn't allow permission
			isRecording: false, //track recording status to toggle record/stop buttons
			isPlaybackActive: false, //need a different videojs player for playback to enable the progress controls
			isPlaybackVideoActive: false, //the playback video is available for control
			recordedCount: 0, //to show 'Record' or 'Re-record' on the button
			isFilenameChangeActive: false, //prompt the filename change modal
			newFilename: '',
			hasFilenameSet: false, //allow the user to set a filename prior to converting
			canConvert: false, //when there's a video available to convert, provide convert button
			isConverting: false, //converting video to mp4, to notify user
			conversionProgress: 0, //to indicate to the user conversion progress
			hasConversionError: false, //converting video to mp4, to notify user
			isUploading: false, //to notify of update
			hasUploadError: false, //to notify of upload error, try again?
			hasUploaded: false //notify UI of upload complete
		}
	},

	computed: {

		/*
		 * To know that you'll be re-recording a video, overwriting the previously recorded
		 *
		 * @returns { String }
		 */
		turnOnText () {
			if (this.mediaType === mediaTypes.CAMERA) {
				return 'Turn on Camera';
			} else if (this.mediaType === mediaTypes.SCREEN) {
				return 'Enable Screen Sharing';
			}
		},

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
		showConvertAndUploadButton () {
			if (this.canConvert && !this.isRecording && !this.isConverting && !this.isUploading) {
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
			if (this.isDeviceReady && !this.isRecording && !this.isConverting && !this.isUploading) {
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
			if (this.isDeviceReady && this.isRecording && !this.isConverting && !this.isUploading) {
				return true;
			} else {
				return false;
			}
		},

		/*
		 * convert the filename to use .mp4 instead of whatever it was
		 *
		 * @returns { String }
		 */
		currentRecordedFilenameToMp4 () {
			return convertFilenameExtension(this.newFilename, '.mp4');
		},

		/*
		 * to set a percentage width on an element
		 *
		 * @returns { String }
		 */
		currentConvertedPercentage () {
			const percentage = (this.conversionProgress * 100.0).toFixed(2);
			return percentage + '%';
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
			this.recordPlayer = videojs(this.$refs.recordingVideo, this.getVideojsOptions(), this.onVideoJsReady);
		},

		/*
		 * to return different options for CAMERA or SCREEN
		 *
		 * @returns {Object}
		 */
		getVideojsOptions () {
			if (this.mediaType === mediaTypes.CAMERA) {
				return getRecordCameraOptions();
			} else if (this.mediaType === mediaTypes.SCREEN) {
				return getRecordScreenOptions();
			}
		},

		/*
		 * videojs is ready, so set up listeners and animate on
		 *
		 */
		onVideoJsReady () {
			try {
				this.recordPlayer.on('deviceReady', () => {
					this.isDeviceReady = true;
				});
				this.animateOn();
				this.initVideoJsRecordListeners();
				this.recordPlayer.record().getDevice();
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
		initVideoJsRecordListeners () {
			this.recordPlayer.on('startRecord', this.onStartRecord);
			this.recordPlayer.on('stopRecord', this.onStopRecord);
			this.recordPlayer.on('finishConvert', this.onFinishConvert);
			this.recordPlayer.on('error', this.onError);
			this.recordPlayer.on('deviceError', this.onDeviceError);
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
		 * onStopRecord
		 *
		 */
		onStopRecord () {
			this.$emit(recordMediaEvents.PROCCESS_STOPPED);
			this.isRecording = false;
			this.recordedCount++;
		},

		/*
		 * the seekable media file is ready
		 *
		 */
		onFinishConvert () {
			this.recordedData = this.recordPlayer.convertedData; // the blob object contains the recorded data that we could convert
			this.canConvert = true; //enable the convert button
			this.isPlaybackActive = true;
		},

		/*
		 * not sure what would cause this trigger yet, but it's in the videojs-record examples, probably an invalid src etc
		 *
		 */
		onError () {
			console.warn(error);
		},

		/*
		 * initVideoErrorListeners
		 *
		 */
		onDeviceError () {
			const errorCode = String(this.recordPlayer.deviceErrorCode);
			console.warn('errorCode = ' + errorCode);
			if (errorCode === 'NotReadableError: Could not start video source' || 'AbortError: Starting video failed') {
				this.hasDeviceAlreadyInUseError = true;
			} else if (errorCode === 'NotAllowedError: Permission denied' || 'NotAllowedError: The request is not allowed by the user agent or the platform in the current context.') {
				this.hasPermissionDeniedError = true;
			}
			console.warn(this.recordPlayer.deviceErrorCode);
		},

		/*
		 * enable the device, initially it's a blank screen until enabled
		 * may request permission on first use
		 *
		 */
		onEnableSelected () {
			this.recordPlayer.record().getDevice();
		},

		/*
		 * start the record
		 *
		 */
		onStartRecordSelected () {
			this.recordPlayer.record().start();
		},

		/*
		 * stop the record
		 *
		 */
		onStopRecordSelected () {
			this.recordPlayer.record().stop();
		},

		/*
		 * present the save file name modal to name the file
		 *
		 */
		onConvertSelected () {
			this.isFilenameChangeActive = true;
		},


		/*
		 * the user has set a file name and selected save
		 *
		 */
		onSaveFilenameSelected () {
			this.isFilenameChangeActive = false;
			this.convertAndUploadVideo();
		},

		/*
		 * convert and upload to SB
		 * 
		 */
		async convertAndUploadVideo () {
			try {
				await this.convertVideo();
				const responseData = await this.uploadConvertedVideo();
				this.postUploadedMessage(responseData);
			} catch (error) {
				this.$emit(recordMediaEvents.PROCCESS_STOPPED);
				console.log(error);
			}
		},

		/*
		 * convert all browser videos to mp4 for consistency
		 * refer to https://github.com/ffmpegwasm/ffmpeg.wasm/blob/master/docs/api.md for further details
		 */
		async convertVideo () {
			try {
				this.$emit(recordMediaEvents.PROCCESS_STARTED);
				this.isConverting = true;
				this.convertedData = await convertToMp4(this.recordedData, this.currentRecordedFilenameToMp4, this.updateConversionProgress) //into blob format for uplaod to Schoolbox
				this.isConverting = false;
			} catch (error) {
				this.$emit(recordMediaEvents.PROCCESS_STOPPED);
				this.isConverting = false;
				this.hasConversionError = true;
				console.log(error);
			}
		},

		/*
		 * to inform the user of progress to track estimated time of completion
		 *
		 * @params { ratio }
		 */
		updateConversionProgress (progress) {
			if (progress < 0) {
				this.conversionProgress = 0;
			} else {
				this.conversionProgress = progress;
			}
		},

		/*
		 * the mp4 is ready, so upload it up to SchoolBox
		 *
		 */
		async uploadConvertedVideo () {
			try {
				this.isUploading = true;
				const formData = new FormData();
				formData.append('upload', this.convertedData, this.currentRecordedFilenameToMp4);
				const response = await axios.post('/storage/asyncUpload.php', formData);
				return response.data;
			} catch (error) {
				this.$emit(recordMediaEvents.PROCCESS_STOPPED);
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
				title:    this.currentRecordedFilenameToMp4,
				mimeType: this.convertedData.type,
				filesize: this.convertedData.size,
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
			if (this.recordPlayer) {
				this.recordPlayer.dispose();
			}
		},

		/*
		 * close the error modal, animating out
		 *
		 */
		onCloseErrorModalSelected () {
			this.$refs.errorModal.animateOff();
		},

		/*
		 * it's closed now, so disable the errors to allow trying again
		 *
		 */
		onErrorModalClosed () {
			this.hasDeviceAlreadyInUseError = false;
			this.hasPermissionDeniedError = false;
			this.hasConversionError = false;
		},

		/*
		 * close the filename modal
		 *
		 */
		onCloseFilenameModalSelected () {
			this.$refs.filenameModal.animateOff();
		},

		/*
		 * close the filename modal, cancel has been selected rather than save
		 *
		 */
		onFilenameModalClosed () {
			this.isFilenameChangeActive = false;
		},

		/*
		 * Pass through the video options to play the latest recorded video
		 *
		 * @returns { Object }
		 */
		getVideoPlayerOptions () {
			return getPlaybackOptions(this.recordedData);
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
						<div class='video-container'>
							<video ref="recordingVideo" playsinline class="video-js"></video>
						</div>
						<div v-if="isPlaybackActive" class='video-container' key="playbackVideoContainer">
							<video-player
								ref="playbackVideo"
								:options="getVideoPlayerOptions()"
								@on-video-ready="onPlaybackVideoReady"
							/>
						</div>
					</div>

					<record-media-modal v-if="hasDeviceAlreadyInUseError || hasPermissionDeniedError || hasConversionError" ref="errorModal" :closed-call-back="onErrorModalClosed" key="errorModal">
						<template v-if="hasDeviceAlreadyInUseError">
			 				 <div class="alert-box warning">The camera appears to be already in use. Please close any Applications or Browser windows that may be using the camera and try again.</div>
							<button @click.prevent="onCloseErrorModalSelected">Close</button>
						</template>
						<template v-else-if="hasPermissionDeniedError">
			 	 			<div class="alert-box alert">Permission to access the device was denied. Please retry and grant access if you wish to proceed.</div>
							<button @click.prevent="onCloseErrorModalSelected">Close</button>
						</template>
						<template v-else-if="hasConversionError">
			  				<div class="alert-box alert">There was an error while converting.</div>
							<button @click.prevent="onCloseErrorModalSelected">Close</button>
						</template>
					</record-media-modal>

					<record-media-modal v-else-if="isFilenameChangeActive" ref="filenameModal" :closed-call-back="onFilenameModalClosed" key="filenameModal">
						<label for="filename">Filename</label>
						<input type="text" id="filename" name="filename" v-model="newFilename">
						<button @click.prevent="onSaveFilenameSelected">Save and convert</button>
						<button @click.prevent="onCloseFilenameModalSelected">Cancel</button>
					</record-media-modal>

					<record-media-modal v-else-if="isConverting" ref="convertingModal" key="convertingModal">
						<!-- <div class="loader"></div> -->
						<div class="progress large-12" style="margin-bottom: 1rem;"><span class="meter" :style="{ width: currentConvertedPercentage }"></span></div>
						<p><em>Converting the video to mp4, please wait...</em></p>
					</record-media-modal>

					<record-media-modal v-else-if="isUploading" ref="uploadingModal" key="uploadingModal">
						<div class="loader"></div>
						<p><em>Uploading video, please wait...</em></p>
					</record-media-modal>
					
					<record-media-modal v-else-if="hasUploaded" ref="uploadedModal" key="uploadedModal">
						<p><em>Video successfully converted &amp; uploaded.</em></p>
					</record-media-modal>

				</div>
			</div>
		</div><!-- .ab-pane -->

		<div class="component-action" ref="actions">
			<section>
				<a role="button" class="button" disabled></a><!-- just to keep the size between all record-x.vue consistent -->
				<a v-if="!isDeviceReady" @click.prevent="onEnableSelected" role="button" class="button" title="Enable Video" href="#turn-on" key="camera">{{ turnOnText }}</a>
				<transition name="fade">
					<a v-if="showConvertAndUploadButton" @click.prevent="onConvertSelected" role="button" class="button button-convert" title="Convert &amp; Upload" href="#stop-recording-video" key="convert">Convert &amp; upload</a>
				</transition>
				<transition name="fade" mode="out-in">
					<a v-if="showRecordButton" @click.prevent="onStartRecordSelected" role="button" class="button" :title="recordButtonText" href="#record-video" key="record">{{ recordButtonText }}</a>
					<a v-else-if="showStopButton" @click.prevent="onStopRecordSelected" role="button" class="button" title="Stop Video" href="#stop-recording-video" key="stop">Stop</a>
				</transition>
				<transition name="fade" mode="out-in">
					<a v-if="isPlaybackVideoActive && !$refs.playbackVideo.isPlaying" @click.prevent="$refs.playbackVideo.play()" role="button" class="button" title="Play Video" href="#play-video" key="play">Play</a>
					<a v-else-if="isPlaybackVideoActive && $refs.playbackVideo.isPlaying" @click.prevent="$refs.playbackVideo.pause()" role="button" class="button" title="Pause Video" href="#pause-video" key="pause">Pause</a>
				</transition>
			</section>
		</div><!-- .component-action -->

	</div>
</template>

<style lang="scss" scoped>

.media-container {
	position: relative;
	width: 100%;
	height: 100%;
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

.meter {
	transition: width 0.1s;
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
