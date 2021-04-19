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

export default {
	
	data () {
		return {
			isDeviceReady: false, //to toggle the device display
			hasDeviceError: false, //can't access the requested device
			isRecording: false, //track recording status to toggle record/stop buttons
			recordedCount: 0, //to show 'Record' or 'Re-record' on the button
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
		}

	},

	created () {
        
		this.player = null; //videojs player
		this.recordedData = null;
		this.convertedData = null;

    },

	mounted () {
        this.initVideojs();
	},

	beforeDestroy () { 
		this.destroyVideojs();
	},

    methods: {

		/*
         * Start it up
         * 
         */ 
        initVideojs () {

            this.player = videojs(this.$refs.myAudio, this.getVideoJsOptions());
			
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
				aspectRatio: '4:3',
				loop: false,
				bigPlayButton: false,
				controlBar: {
					volumePanel: true,
				},
				plugins: {
					wavesurfer: { // this plugin is only needed when recording audio-only
						backend: 'WebAudio',
						waveColor: '#eeefda',
						progressColor: '#FAFCD2',
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
						maxLength: 20,
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
				const response = await axios.post('/storage/asyncUpload.php', this.getFormData());
				this.postUploadedMessage(response.data);

			} catch (error) {
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
			formData.append('upload', this.recordedData, 'test.mp3');
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
				title:    'test.mp3',
				mimeType: this.recordedData.type,
				filesize: this.recordedData.size,
				hash:     responseData.hash
			};
			window.top.postMessage(JSON.stringify({ data: [data] }), location.origin);
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
		}

    }

}
</script>

<template>
	<div>
		<div class="ab-pane">
            <div>
                <audio id="myAudio" ref="myAudio" class="video-js vjs-default-skin"></audio>
            </div>
        </div>
		<div class="component-action recorderButtons">
			<section>
				<a v-if="recordedCount > 0" @click.prevent="onUploadSelected" role="button" class="button" data-interaction="audio-record-stop" title="Upload Video" href="#stop-recording-video">Upload</a>
				<a v-if="!isDeviceReady" @click.prevent="onEnableSelected" role="button" class="button" title="Enable Video" href="#enable-video">Enable Audio</a>
				<a v-else-if="isDeviceReady && !isRecording" @click.prevent="onStartRecordSelected" role="button" class="button" data-interaction="audio-record-play" title="Record Video" href="#record-video">{{ recordButtonText }}</a>
				<a v-else-if="isDeviceReady && isRecording" @click.prevent="onStopRecordSelected" role="button" class="button" data-interaction="audio-record-stop" title="Stop Video" href="#stop-recording-video">Stop</a>
			</section>
		</div>
	</div>
</template>

<style lang="scss">

#myAudio {
    background-color: #9FD6BA;
}

.vjs-icon-record-start, .vjs-icon-record-stop {
	min-height: 0;
}

</style>