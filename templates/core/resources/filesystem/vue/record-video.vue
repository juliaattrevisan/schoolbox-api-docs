<script>

/*
 * To record some video
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
import { createFFmpeg, fetchFile } from '@ffmpeg/ffmpeg';
import { gsap } from 'gsap/all';
import RecordMediaModal from './record-media-modal';
import VideoPlayer from '@schoolbox/videojs/video-player';


export default {
	
	components: {
		RecordMediaModal,
		VideoPlayer
	},

	data () {
		return {
			isDeviceReady: false, //to toggle the device display
			hasDeviceError: false, //can't access the requested device
			isRecording: false, //track recording status to toggle record/stop buttons
			isPlaybackActive: false, //need a different videojs player for playback to enable the progress controls
			recordedCount: 0, //to show 'Record' or 'Re-record' on the button
			canConvert: false, //when there's a video available to convert, provide convert button
			isConverting: false, //converting video to mp4, to notify user
			conversionProgress: 0, //to indicate to the user conversion progress
			hasConversionError: false, //converting video to mp4, to notify user
			isUploading: false, //to notify of update
			hasUploadError: false, //to notify of upload error, try again?
			hasUploaded: false, //notify UI of upload complete
		}
	},

	computed: {

		/*
         * To know that you'll be re-recording a video, overwriting the previously recorded
         * 
		 * @params { String }
         */ 
		recordButtonText () {
			if (this.recordedCount == 0) {
				return 'Record';
			} else {
				return 'Re-Record';
			}
		},

		/*
         * To know whether to provide the convert button
         * 
		 * @params { Boolean }
         */ 
		showConvertButton () {
			if (this.canConvert && !this.isConverting && !this.isUploading) {
				return true;
			} else {
				return false;
			}
		},

		/*
         * To know whether to provide the record button
         * 
		 * @params { Boolean }
         */ 
		showRecordButton () {
			if (this.isDeviceReady && !this.isRecording && !this.isConverting && !this.isUploading) {
				return true;
			} else {
				return false;
			}
		},

		/*
         * To know whether to provide the stop button
         * 
		 * @params { Boolean }
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
		 * @params { String }
         */ 
		currentRecordedFilenameToMp4 () {
			const filenameWithoutExtension = this.recordedData.name.replace(/\.[^/.]+$/, "");
			return filenameWithoutExtension + '.mp4';
		},

	},

	mounted () {
		this.updateContainerHeight();
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
         * Start it up
         * 
         */ 
        initVideojs () {

			gsap.set(this.$refs.recordingVideoContainer, { alpha: 0 } );
			gsap.set(this.$refs.actions, { alpha: 0 } );

            this.recordPlayer = videojs(this.$refs.recordingVideo, this.getVideoJsRecordOptions(), () => {
				gsap.to(this.$refs.recordingVideoContainer, 0.5, { alpha: 1 } );
				gsap.to(this.$refs.actions, 0.4, { alpha: 1, delay: 0.2 } );
			});
			
			this.recordPlayer.on('deviceReady', () => {
				this.isDeviceReady = true;
			});

			this.initVideoRecordListeners();
			this.initVideoErrorListeners();

        },

		/*
         * the VideoJS/VideoJS-record params to set up
		 * refer to https://collab-project.github.io/videojs-record/#/options and https://docs.videojs.com/ for options
         * 
         */ 
        getVideoJsRecordOptions () {
			return {
				controls: true,
				autoplay: false,
				fluid: true,
				aspectRatio: '16:9',
				loop: false,
				bigPlayButton: false,
				controlBar: {
					volumePanel: false,
				},
				plugins: {
					record: {
						audio: true,
						video: {
							width: { min: 640, ideal: 640, max: 1280 }, // video media constraints: set resolution of camera
							height: { min: 360, ideal: 360, max: 720 }
						},
						maxLength: 300, //five minutes
						convertEngine: 'ts-ebml', //to be able to have seekable webm files
						debug: true,
					}
				}
			}
		},

		/*
         * the VideoJS/VideoJS-record params to set up
		 * refer to https://collab-project.github.io/videojs-record/#/options and https://docs.videojs.com/ for options
         * 
         */ 
        getVideoJsPlaybackOptions () {
			return {
				autoplay: false,
				controls: true,
				fluid: true,
				aspectRatio: '16:9',
				bigPlayButton: true,
				controlBar: {
					pictureInPictureToggle: false
				},
				sources: [
					{
						src: URL.createObjectURL(this.recordedData),
						type: this.recordedData.type
					}
				]
			}
		},

		/*
         * the user has started/stopped recording a video
         * 
         */ 
        initVideoRecordListeners () {
            
			this.recordPlayer.on('startRecord', () => { // user clicked the record button and started recording
				this.isRecording = true;
				this.canConvert = false; //disable the convert button
				this.isPlaybackActive = false;
			});

			this.recordPlayer.on('stopRecord', () => { // and stopped recording
				this.recordedCount++;
				this.isRecording = false;
			});

			this.recordPlayer.on('finishRecord', () => { // user completed recording and stream is available
				this.recordedData = this.recordPlayer.recordedData; // the blob object contains the recorded data that we could convert
				this.canConvert = true; //enable the convert button
				this.isPlaybackActive = true;
			});

        },

		/*
         * convert all browser videos to mp4 for consistency
         * refer to https://github.com/ffmpegwasm/ffmpeg.wasm/blob/master/docs/api.md for further details
         */ 
        async convertVideo () {
			try {
				this.$emit('on-process-started');
				this.isConverting = true;
				this.ffmpeg = createFFmpeg({
					log: true,
					// progress: p => console.log(p)
				});
				await this.ffmpeg.load(); //load in ffmpeg for use
				this.ffmpeg.FS('writeFile', this.recordedData.name, await fetchFile(this.recordedData)); //save first
				await this.ffmpeg.run('-i', this.recordedData.name, this.currentRecordedFilenameToMp4); //then run conversion
				const data = this.ffmpeg.FS('readFile', this.currentRecordedFilenameToMp4); //the raw mp4 data
				this.convertedData = new Blob([data.buffer], { type: 'video/mp4' }); //into blob format for uplaod to Schoolbox
				this.isConverting = false;
				this.uploadConvertedVideo();
			} catch (error) {
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
			for (const [key, value] of Object.entries(progress)) {
				console.log('PROGRESS');
				console.log(`${key}: ${value}`);
			}
		},

		/*
         * the mp4 is ready, so upload it up to SchoolBox
         * 
         */ 
        async uploadConvertedVideo () {
			try {
				this.isUploading = true;
				const response = await axios.post('/storage/asyncUpload.php', this.getFormData());
				this.postUploadedMessage(response.data);
			} catch (error) {
				this.isUploading = false;
				this.hasUploadError = true;
				console.log(error);
			}
		},

		/*
         * Prep the data to FormData so Schoolbox accepts
         * 
		 * @returns { FormData }
         */ 
        getFormData () {
			const formData = new FormData();
			formData.append('upload', this.convertedData, this.currentRecordedFilenameToMp4);
			return formData;
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
				this.$emit('on-process-ended');
			}, 2000);
		},

		/*
         * initVideoErrorListeners
         * 
         */ 
        initVideoErrorListeners () {
			
			this.recordPlayer.on('error', (element, error) => { // error handling, not sure what would cause this trigger yet, but it's in the videojs-record examples
				console.warn(error);
			});

			this.recordPlayer.on('deviceError', () => { //User doesn't allow the browser to access the webcam and/or microphone. Check player.deviceErrorCode for the specific error code.
				this.hasDeviceError = true;
			});

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
         * convert the video to mp4 format for consistent video format across browsers
         * 
         */ 
        onConvertSelected () {
			this.convertVideo();
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
         * close the uploading modal
         * 
         */ 
        onCloseUploadedModalSelected () {
			this.$refs.uploadedModal.animateOff();
		},

		/*
         * it's closed now, so disable the error to allow trying again
         * 
         */ 
        onUploadedModalClosed () {
			this.hasUploaded = false;
		},

	}

}
</script>

<template>
	<div>
		<div class="ab-pane">
            <div>
                <div class='media-container' ref="mediaContainer">
					<div ref='recordingVideoContainer' class='video-container'>
						<video ref="recordingVideo" playsinline class="video-js vjs-default-skin"></video>
					</div>
					<div v-if="isPlaybackActive" ref="playbackVideoContainer" class='video-container'>
						<video-player :options="getVideoJsPlaybackOptions()"></video-player>
					</div>
					<record-media-modal v-if="hasDeviceError" ref="errorModal" :closed-call-back="onErrorModalClosed" key="errorModal">
						<h3>The camera appears to be already in use. Please close any Applications our Browser windows that may be using the camera and try again.</h3>
						<button @click.prevent="onCloseErrorModalSelected">Close</button>
					</record-media-modal>
					<record-media-modal v-else-if="isConverting" ref="convertingModal" key="convertingModal">
						<div class="loader"></div>
						<em>Converting the video to mp4, please wait...</em>
					</record-media-modal>
					<record-media-modal v-else-if="isUploading" ref="uploadingModal" key="uploadingModal">
						<div class="loader"></div>						
						<em>Uploading video, please wait...</em>
					</record-media-modal>
					<record-media-modal v-else-if="hasUploaded" ref="uploadedModal" key="uploadedModal">
						<em>Video succesfully converted &amp; uploaded.</em>
					</record-media-modal>
				</div>
            </div>
        </div>
		<div class="component-action" ref="actions">
			<section>
				<a role="button" class="button" disabled></a><!-- just to keep the size between all record-x.vue consistent -->
				<a v-if="!isDeviceReady" @click.prevent="onEnableSelected" role="button" class="button" title="Enable Video" href="#enable-video" key="camera">Turn on Camera</a>
				<transition name="fade">
					<a v-if="showConvertButton" @click.prevent="onConvertSelected" role="button" class="button button-convert" data-interaction="audio-record-stop" title="Convert &amp; Upload" href="#stop-recording-video" key="convert">Convert &amp; Upload</a>
				</transition>
				<transition name="fade" mode="out-in">
					<a v-if="showRecordButton" @click.prevent="onStartRecordSelected" role="button" class="button" data-interaction="audio-record-play" :title="recordButtonText" href="#record-video" key="record">{{ recordButtonText }}</a>
					<a v-if="showStopButton" @click.prevent="onStopRecordSelected" role="button" class="button" data-interaction="audio-record-stop" title="Stop Video" href="#stop-recording-video" key="stop">Stop</a>
				</transition>
			</section>
		</div>
	</div>
</template>

<style lang="scss">

.media-container {
	position: relative;
    width: 100%;
	height: 100%;
    background: black;
    overflow: hidden;
}

.video-container {
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

.button-convert {
	margin-right: auto;
}

</style>