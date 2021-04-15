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


export default {
	
	data () {
		return {
			player: '',
			options: {
				controls: true,
				autoplay: false,
				fluid: false,
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
						// convertEngine: 'ffmpeg.wasm', // enable ffmpeg.wasm plugin
						// convertWorkerURL: '../../node_modules/@ffmpeg/core/dist/ffmpeg-core.js',
						// convertOptions: ['-c:v', 'libx264', '-preset', 'slow', '-crf', '22', '-c:a', 'copy', '-f', 'mp4'], // convert recorded data to MP4 (and copy over audio data without encoding)
						// pluginLibraryOptions: {
						// 	outputType: 'video/mp4' // specify output mime-type
						// }
					}
				},
			}
		};
	},

	mounted () {
		debugger;
		this.initVideojs();
		this.initVideoRecordListeners();
		this.initVideoConvertListeners();
		this.initVideoErrorListeners();
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
            this.player = videojs(this.$refs.myVideo, this.options, () => {
				// print version information at startup
				var msg = 'Using video.js ' + videojs.VERSION +
					' with videojs-record ' + videojs.getPluginVersion('record') +
					' and recordrtc ' + RecordRTC.version;
				videojs.log(msg);
			});
			// device is ready
			this.player.on('deviceReady', () => {
				console.log('device is ready!');
			});
        },

		/*
         * initVideoRecordListeners
         * 
         */ 
        initVideoRecordListeners () {
            // user clicked the record button and started recording
			this.player.on('startRecord', () => {
				console.log('started recording!');
			});

			// user completed recording and stream is available
			this.player.on('finishRecord', () => {
				// the blob object contains the recorded data that
				// can be downloaded by the user, stored on server etc.
				console.log('finished recording: ', this.player.recordedData);
			});
        },

		/*
         * initVideoConvertListeners
         * 
         */ 
        initVideoConvertListeners () {
            // converter started processing
			this.player.on('startConvert', () => {
				console.log('started converting!');
			});

			// converter completed and stream is available
			this.player.on('finishConvert', () => {
				// the convertedData object contains the recorded data that
				// can be downloaded by the user, stored on server etc.
				console.log('finished converting: ', this.player.convertedData);
			});
        },

		/*
         * initVideoErrorListeners
         * 
         */ 
        initVideoErrorListeners () {
			// error handling
			this.player.on('error', (element, error) => {
				console.warn(error);
			});

			this.player.on('deviceError', () => {
				console.error('device error:', this.player.deviceErrorCode);
			});
		},

		/*
         * destroyVideojs
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