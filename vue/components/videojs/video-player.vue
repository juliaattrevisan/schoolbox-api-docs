<script>

/*
 * To play a video
 *
 */

import 'video.js/dist/video-js.css';
import videojs from 'video.js';
import recordMediaEvents from '@shared/record-media/recordMediaEvents';

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

.video-js {
  .vjs-control-bar {
	height: 4em;
  }
  .vjs-control {
	width: 5em;
  }
  .vjs-volume-bar {
	margin: 1.9em 0.45em;
  }
  .vjs-slider-horizontal .vjs-volume-level::before {
	top: -0.4em;
  }
  .vjs-time-control {
	color: white;
	font-size: .875rem;
	padding-left: .5rem;
	padding-right: .5rem;
	width: auto;
	min-width: 0;
  }
  .vjs-button > .vjs-icon-placeholder::before {
	color: white;
	font-size: 2.8em;
	line-height: 1.4;
  }
  .vjs-big-play-button {
	background-color: black;
	border-color: white;
	top: 50%;
	left: 50%;
	margin-left: -45px;
	margin-top: -24px
  }
  .vjs-progress-holder,
  .vjs-matrix .vjs-play-progress,
  .vjs-matrix .vjs-slider-bar,
  .vjs-volume-panel .vjs-volume-control,
  .vjs-big-play-button .vjs-icon-placeholder::before {
	color: white;
  }
  .vjs-matrix .vjs-volume-level,
  .vjs-matrix .vjs-play-progress,
  .vjs-matrix .vjs-slider-bar {
	background: white;
  }
}

.vjs-record.video-js {
  .vjs-control.vjs-record-indicator {
	line-height: 1.4;
	width: 1em;
	height: 1em;
  }
  .vjs-control.vjs-record-indicator::before {
	font-size: 1em;
  }
  .vjs-control.vjs-record-indicator::after {
	height: calc(1em - 4px);
	width: calc(1em - 4px);
	top: 3px;
	left: 3px;
  }
}

</style>
