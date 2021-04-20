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
import WaveSurfer from 'wavesurfer.js';
import MicrophonePlugin from 'wavesurfer.js/dist/plugin/wavesurfer.microphone.js';
WaveSurfer.microphone = MicrophonePlugin;
import videojs_wavesurfer_css from 'videojs-wavesurfer/dist/css/videojs.wavesurfer.css';
import Wavesurfer from 'videojs-wavesurfer/dist/videojs.wavesurfer.js';
import Record from 'videojs-record/dist/videojs.record.js';
import VmsgEngine from 'videojs-record/dist/plugins/videojs.record.vmsg.js';
import { gsap } from 'gsap/all';
import RecordMediaModal from './record-media-modal';


export default {
	
	components: {
		RecordMediaModal
	},

	data () {
		return {
			isDeviceReady: false, //to toggle the device display
			hasDeviceError: false, //can't access the requested device
			isRecording: false, //track recording status to toggle record/stop buttons
			recordedCount: 0, //to show 'Record' or 'Re-record' on the button
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
         * To know whether to provide the record button
         * 
		 * @params { Boolean }
         */ 
		showRecordButton () {
			if (this.isDeviceReady && !this.isRecording && !this.isUploading) {
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
			if (this.isDeviceReady && this.isRecording && !this.isUploading) {
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
		currentRecordedFilenameToMp3 () {
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

			gsap.set(this.$refs.audioContainer, { alpha: 0 } );
			gsap.set(this.$refs.actions, { alpha: 0 } );

            this.player = videojs(this.$refs.myAudio, this.getVideoJsOptions(), () => {
				gsap.to(this.$refs.audioContainer, 0.5, { alpha: 1 } );
				gsap.to(this.$refs.actions, 0.4, { alpha: 1, delay: 0.2 } );
			});
			
			this.player.on('deviceReady', () => {
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
        getVideoJsOptions () {
			return {
				controls: true,
				autoplay: false,
				fluid: true,
				aspectRatio: '16:9',
				loop: false,
				bigPlayButton: false,
				controlBar: {
					volumePanel: true,
				},
				plugins: {
					wavesurfer: { // this plugin is only needed when recording audio-only
						backend: 'WebAudio',
						waveColor: '#fff',
						progressColor: '#ccc',
						debug: true,
						cursorWidth: 1,
						displayMilliseconds: true,
						hideScrollbar: true,
						plugins: [
							WaveSurfer.microphone.create({ // enable microphone plugin
								bufferSize: 4096,
								numberOfInputChannels: 1,
								numberOfOutputChannels: 1,
								constraints: {
									video: false,
									audio: true
								}
							})
						]
					},
					record: {
						audio: true,
						video: false,
						maxLength: 300, //5 minutes
						audioEngine: 'vmsg',
    					audioWebAssemblyURL: '/static/javascript/vendor/vmsg/vmsg.wasm',
    					// audioWebAssemblyURL: 'https://unpkg.com/vmsg/vmsg.wasm', //currently issues with loading .wasm files...
						debug: true,
					}
				}
			}
		},

		/*
         * the user has started/stopped recording a audio
         * 
         */ 
        initVideoRecordListeners () {
            
			this.player.on('startRecord', () => { // user clicked the record button and started recording
				this.isRecording = true;
				this.canConvert = false; //disable the convert butto
			});

			this.player.on('stopRecord', () => { // and stopped recording
				this.recordedCount++;
				this.isRecording = false;
			});

			this.player.on('finishRecord', () => { // user completed recording and stream is available
				this.recordedData = this.player.recordedData; // the blob object contains the recorded data that we could convert
			});

        },

		/*
         * the mp3 is ready, so upload it up to SchoolBox
         * 
         */ 
        async uploadAudio () {
			try {
				this.$emit('on-process-started');
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
			formData.append('upload', this.recordedData, this.currentRecordedFilenameToMp3);
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
				title:    this.currentRecordedFilenameToMp3,
				mimeType: this.recordedData.type,
				filesize: this.recordedData.size,
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
			
			this.player.on('error', (element, error) => { // error handling, not sure what would cause this trigger yet, but it's in the videojs-record examples
				console.warn(error);
			});

			this.player.on('deviceError', () => { //User doesn't allow the browser to access the webcam and/or microphone. Check player.deviceErrorCode for the specific error code.
				this.hasDeviceError = true;
				console.error('device error:', this.player.deviceErrorCode);
			});

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
        onUploadSelected () {
			this.uploadAudio();
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

    }

}
</script>

<template>
	<div>
		<div class="ab-pane">
            <div>
				<div class='media-container' ref="mediaContainer">
					<div ref='audioContainer' class='audio-container'>
						<audio id="myAudio" ref="myAudio" class="video-js vjs-default-skin"></audio>
					</div>
					<record-media-modal v-if="hasDeviceError" ref="errorModal" :closed-call-back="onErrorModalClosed" key="errorModal">
						<h3>The camera appears to be already in use. Please close any Applications our Browser windows that may be using the camera and try again.</h3>
						<button @click.prevent="onCloseErrorModalSelected">Close</button>
					</record-media-modal>
					<record-media-modal v-else-if="isUploading" ref="uploadingModal" key="uploadingModal">
						<div class="loader"></div>						
						<em>Uploading audio, please wait...</em>
					</record-media-modal>
					<record-media-modal v-else-if="hasUploaded" ref="uploadedModal" key="uploadedModal">
						<em>Audio succesfully uploaded.</em>
					</record-media-modal>
				</div>
			</div>
        </div>		
		<div class="component-action" ref="actions">
			<section>
				<a role="button" class="button" disabled></a><!-- just to keep the size between all record-x.vue consistent -->
				<a v-if="!isDeviceReady" @click.prevent="onEnableSelected" role="button" class="button" title="Enable Video" href="#turn-on-video">Turn on Audio</a>
				<transition name="fade">
					<a v-if="recordedCount > 0" @click.prevent="onUploadSelected" role="button" class="button"  title="Upload Audio" href="#upload-audio">Upload Audio</a>
				</transition>
				<transition name="fade" mode="out-in">
					<a v-if="showRecordButton" @click.prevent="onStartRecordSelected" role="button" class="button" title="Record Video" href="#record-audio">{{ recordButtonText }}</a>
					<a v-else-if="showStopButton" @click.prevent="onStopRecordSelected" role="button" class="button" title="Stop Video" href="#stop-recording-audio">Stop</a>
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