<script>

/*
 * To play a video
 *
 */

import 'video.js/dist/video-js.css';
import videojs from 'video.js';
import recordMediaEvents from '../../../templates/core/resources/filesystem/js/recordMediaEvents'; //hmm, put this somewhere else?

export default {
	
	props: {
		options: {
			type: Object,
			required: true,
			default () {
				return {};
			}
		},
	},

	data () {
		return {
			player: null,
			isPlaying: false
		}
	},

	mounted () {
		this.initVideojs();
	},

	beforeDestroy () {
		if (this.player) {
			this.player.dispose();
		}
	},

	methods: {

		/*
		 * Start it up
		 * 
		 */ 
		initVideojs () {
			this.player = videojs(this.$refs.videoPlayer, this.options, () => {
				this.$emit(recordMediaEvents.VIDEO_READY);
			});
			this.player.on('play', () => { 
				this.isPlaying = true;
			});
			this.player.on('pause', () => {  
				this.isPlaying = false;
			});
			this.player.on('ended', () => { 
				this.isPlaying = false;
			});
		},

		/*
		 * for template/external control
		 * 
		 */ 
		play () {
			this.player.play();
		},

		/*
		 * for template/external control
		 * 
		 */ 
		pause () {
			this.player.pause();
		}

	}

}
</script>

<template>
	<div>
		<video ref="videoPlayer" class="video-js vjs-matrix"></video>
	</div>
</template>

<style lang="scss">

//DEMO - see https://docs.videojs.com/tutorial-skins.html to update further

/* Change all text and icon colors in the player. */
.vjs-matrix.video-js {
  color: #00ff00;
}

/* Change the border of the big play button. */
.vjs-matrix .vjs-big-play-button {
  border-color: #00ff00;
}

/* Change the color of various "bars". */
.vjs-matrix .vjs-volume-level,
.vjs-matrix .vjs-play-progress,
.vjs-matrix .vjs-slider-bar {
  background: #00ff00;
}

</style>