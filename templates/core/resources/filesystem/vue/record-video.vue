<script>

/*
 * To record some video
 *
 */

import Vue from 'vue';
import 'video.js/dist/video-js.css';
import 'videojs-record/dist/css/videojs.record.css';
import videojs from 'video.js';
import 'webrtc-adapter';
import RecordRTC from 'recordrtc';
import Record from 'videojs-record/dist/videojs.record.js';
import FFmpegWasmEngine from 'videojs-record/dist/plugins/videojs.record.ffmpeg-wasm.js';
import axios from 'axios';


export default {
	
	data () {
		return {
			isDeviceReady: false, //to toggle the device display
			hasDeviceError: false, //can't access the requested device
			isRecording: false, //track recording status
			recordedData: null, 
			isConverting: false, //converting video to mp4
			convertedData: null,
			player: '', //videojs player
			options: {
				controls: true,
				autoplay: false,
				fluid: true,
				ratio: '16:9',
				loop: false,
				width: 320,
				height: 240,
				bigPlayButton: true,
				controlBar: {
					volumePanel: true
				},
				plugins: {
					record: {
						audio: true,
						video: {
							// video media constraints: set resolution of camera
							width: { min: 640, ideal: 640, max: 1280 },
							height: { min: 480, ideal: 480, max: 920 }
						},
						maxLength: 20,
						debug: true,
						convertEngine: 'ffmpeg.wasm', // enable ffmpeg.wasm plugin
						convertWorkerURL: '/static/javascript/vendor/ffmpeg/ffmpeg-core.js', //point to ffmpeg directory
						convertOptions: ['-c:v', 'libx264', '-preset', 'slow', '-crf', '22', '-c:a', 'copy', '-f', 'mp4'], // convert recorded data to MP4 (and copy over audio data without encoding) https://ffmpeg.org/ffmpeg.html#toc-Main-options
						pluginLibraryOptions: {
							outputType: 'video/mp4' // specify output mime-type
						}
					}
				},
			}
		};
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

            this.player = videojs(this.$refs.myVideo, this.options);
			
			this.player.on('deviceReady', () => {
				this.isDeviceReady = true;
			});

			this.initVideoRecordListeners();
			this.initVideoConvertListeners();
			this.initVideoErrorListeners();

        },

		/*
         * the user has started/stopped recording a video
         * 
         */ 
        initVideoRecordListeners () {
            
			this.player.on('startRecord', () => { // user clicked the record button and started recording
				this.isRecording = true;
			});

			this.player.on('stopRecord', () => { // and stopped recording
				this.isRecording = false;
			});

			this.player.on('finishRecord', () => { // user completed recording and stream is available
				this.recordedData = this.player.recordedData; // the blob object contains the recorded data that
			});

        },

		/*
         * conert all browser videos to mp4 for consistency
         * 
         */ 
        initVideoConvertListeners () {
            
			this.player.on('startConvert', () => { // converter started processing
				this.isConverting = true;
			});

			this.player.on('finishConvert', () => { // converter completed and stream is available
				this.convertedData = this.player.convertedData; // the convertedData object contains the recorded data that
				this.uploadConvertedFile();
			});

        },

		/*
         * the mp4 is ready, so upload it up to SchoolBox
         * 
         */ 
        async uploadConvertedFile () {

			try {
				
				const formData = new FormData();
				formData.append('upload', this.player.convertedData, this.player.convertedData.name);

				const response = await axios.post('/storage/asyncUpload.php', formData);
				this.postUploadMessage(response.data);

			} catch (error) {
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
        postUploadMessage (data) {
			const data = {
				title:    this.player.convertedData.name,
				mimeType: this.player.convertedData.type,
				filesize: this.player.convertedData.size,
				hash:     response.data.hash
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
		}

	}

}
</script>

<template>
	<div>
		<video id="myVideo" ref="myVideo" playsinline class="video-js vjs-default-skin"></video>
	</div>
</template>

<style lang="scss" scoped>

#myVideo {
  background-color: #1a535c;
}

</style>