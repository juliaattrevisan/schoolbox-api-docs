<script>

/*
 * To play an audio only, which contains waveform visualizer
 *
 */

import 'video.js/dist/video-js.css';
import 'videojs-wavesurfer/dist/css/videojs.wavesurfer.css'
import videojs from 'video.js';
import WaveSurfer from 'wavesurfer.js'
import Wavesurfer from 'videojs-wavesurfer/dist/videojs.wavesurfer.js'


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

    mounted () {
        this.initVideojs();
    },

    beforeDestroy () {
        this.destroyVideojs();
    },

    methods: {

        /*
         * set up videojs
         *
         */
        initVideojs () {
            this.player = videojs(this.$refs.audioPlayer, this.options, this.onVideojsReady);
        },

         /*
         * videojs is ready, dispatch event in case anyone listening
         *
         */
        onVideojsReady () {
            this.player.src({
                src: this.options.audioPlayerSrc, 
                type: this.options.audioPlayerType
            });
			this.$emit('on-ready');
        },

        /*
         * destroy the instance for garbage collection
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
        <audio ref="audioPlayer" class="video-js vjs-custom-skin"></audio>
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
  .vjs-custom-skin .vjs-play-progress,
  .vjs-custom-skin .vjs-slider-bar,
  .vjs-volume-panel .vjs-volume-control,
  .vjs-big-play-button .vjs-icon-placeholder::before {
    color: white;
  }
  .vjs-custom-skin .vjs-volume-level,
  .vjs-custom-skin .vjs-play-progress,
  .vjs-custom-skin .vjs-slider-bar {
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
