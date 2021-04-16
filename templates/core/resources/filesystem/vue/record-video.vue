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
import axios from 'axios';
import { createFFmpeg, fetchFile } from '@ffmpeg/ffmpeg';


export default {
	
	data () {
		return {
			isDeviceReady: false, //to toggle the device display
			hasDeviceError: false, //can't access the requested device
			isRecording: false, //track recording status to toggle record/stop buttons
			canConvert: false, //when there's a video available to convert, provide convert button
			isConverting: false, //converting video to mp4, to notify user
			conversionProgress: 0 //to indicate to the user conversion progress
		}
	},

	created () {
        
		this.player = null; //videojs player
		this.recordedData = null;
		this.convertedData = null;

		this.ffmpeg = createFFmpeg({
			log: true,
			progress: p => updateConversionProgress(p)
		});

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

            this.player = videojs(this.$refs.myVideo, this.getVideoJsOptions());
			
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
				bigPlayButton: true,
				controlBar: {
					volumePanel: true
				},
				plugins: {
					record: {
						audio: true,
						video: {
							width: { min: 640, ideal: 640, max: 1280 }, // video media constraints: set resolution of camera
							height: { min: 480, ideal: 480, max: 920 }
						},
						maxLength: 20,
						debug: true,
					}
				}
			}
		},

		/*
         * the user has started/stopped recording a video
         * 
         */ 
        initVideoRecordListeners () {
            
			this.player.on('startRecord', () => { // user clicked the record button and started recording
				this.isRecording = true;
				this.canConvert = false; //disable the convert butto
			});

			this.player.on('stopRecord', () => { // and stopped recording
				this.isRecording = false;
			});

			this.player.on('finishRecord', () => { // user completed recording and stream is available
				this.recordedData = this.player.recordedData; // the blob object contains the recorded data that we could convert
				this.canConvert = true; //enable the convert button
			});

        },

		/*
         * convert all browser videos to mp4 for consistency
         * refer to https://github.com/ffmpegwasm/ffmpeg.wasm/blob/master/docs/api.md for further details
         */ 
        async convertVideo () {

			await this.ffmpeg.load(); //load in ffmpeg for use
			this.ffmpeg.FS('writeFile', this.recordedData.name, await fetchFile(this.recordedData)); //save first
			await this.ffmpeg.run('-i', this.recordedData.name, 'test.mp4'); //then run conversion
			const data = this.ffmpeg.FS('readFile', 'test.mp4'); //the raw mp4 data
			this.convertedData = new Blob([data.buffer], { type: 'video/mp4' }); //into blob format for uplaod to Schoolbox
			
			this.uploadConvertedVideo();

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
			formData.append('upload', this.convertedData, 'test.mp4');
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
				title:    'test.mp4',
				mimeType: this.convertedData.type,
				filesize: this.convertedData.size,
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
        onConvertSelected () {
			this.convertVideo();
		}

	}

}
</script>

<template>
	<div>
		<div class="ab-pane">
            <div>
                <video id="myVideo" ref="myVideo" playsinline class="video-js vjs-default-skin"></video>
            </div>
        </div>
		<div class="component-action recorderButtons">
			<section>
				<a v-if="canConvert" @click.prevent="onConvertSelected" role="button" class="button" data-interaction="audio-record-stop" title="Stop Video" href="#stop-recording-video">Convert &amp; Upload</a>
				<a v-if="!isDeviceReady" @click.prevent="onEnableSelected" role="button" class="button" title="Enable Video" href="#enable-video">Enable Video</a>
				<a v-else-if="isDeviceReady && !isRecording" @click.prevent="onStartRecordSelected" role="button" class="button" data-interaction="audio-record-play" title="Record Video" href="#record-video">Record</a>
				<a v-else-if="isDeviceReady && isRecording" @click.prevent="onStopRecordSelected" role="button" class="button" data-interaction="audio-record-stop" title="Stop Video" href="#stop-recording-video">Stop</a>
				<a role="button" class="button cancel" href="#close-modal">Cancel</a>
			</section>
		</div>
	</div>
</template>

<style lang="scss" scoped>

#myVideo {
  background-color: #1a535c;
}

</style>