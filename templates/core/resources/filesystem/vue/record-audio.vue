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

// the following imports are only needed when you're recording
// audio-only using the videojs-wavesurfer plugin
import WaveSurfer from 'wavesurfer.js';
import MicrophonePlugin from 'wavesurfer.js/dist/plugin/wavesurfer.microphone.js';
WaveSurfer.microphone = MicrophonePlugin;

// register videojs-wavesurfer plugin
import videojs_wavesurfer_css from 'videojs-wavesurfer/dist/css/videojs.wavesurfer.css';
import Wavesurfer from 'videojs-wavesurfer/dist/videojs.wavesurfer.js';


import Record from 'videojs-record/dist/videojs.record.js';

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
				bigPlayButton: false,
				controlBar: {
					volumePanel: false
				},
				plugins: {
					// this section is only needed when recording audio-only
					wavesurfer: {
						backend: 'WebAudio',
						waveColor: '#36393b',
						progressColor: 'black',
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
						debug: true
					}
				}
			}
		};
	},

	mounted () {
        this.initVideojs();
		this.initAudioRecordListeners();
		this.initAudioErrorListeners();
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
            this.player = videojs(this.$refs.myAudio, this.options, () => {
                // print version information at startup
                var msg = 'Using video.js ' + videojs.VERSION +
                    ' with videojs-record ' + videojs.getPluginVersion('record') +
                    ', videojs-wavesurfer ' + videojs.getPluginVersion('wavesurfer') +
                    ', wavesurfer.js ' + WaveSurfer.VERSION + ' and recordrtc ' +
                    RecordRTC.version;
                videojs.log(msg);
            });
			// device is ready
			this.player.on('deviceReady', () => {
				console.log('device is ready!');
			});
        },

		/*
         * initAudioRecordListeners
         * 
         */ 
        initAudioRecordListeners () {
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
         * initAudioErrorListeners
         * 
         */ 
        initAudioErrorListeners () {
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
	    <audio id="myAudio" ref="myAudio" class="video-js vjs-default-skin"></audio>
    </div>
</template>

<style lang="scss" scoped>

#myAudio {
  background-color: #1a535c;
}

</style>