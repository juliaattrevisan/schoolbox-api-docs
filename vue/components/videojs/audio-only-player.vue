<script>

/*
 * To play an audio only, which contains waveform visualizer
 *
 */

import 'video.js/dist/video-js.css';
import 'videojs-wavesurfer/dist/css/videojs.wavesurfer.css'
import videojs from 'video.js';
import { swapElements } from '@shared/util/domTools';


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

        /**
         * set up videojs
         *
         */
        initVideojs () {
            this.player = videojs(this.$refs.audioPlayer, this.options, this.onVideojsReady);
            this.player.on('waveReady', this.onWaveformReady);
        },

        /** 
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

        /** 
         * the waveform has been put into the video component
         *
         */
        onWaveformReady () {
            this.$nextTick(() => { //can I put the element behind so can just toggle playback on select?
                const waveformElement = document.querySelector('.vjs-wavedisplay');
                const trackDisplayElement = document.querySelector('.vjs-text-track-display');
                swapElements(waveformElement, trackDisplayElement);
            });
            console.warn('audio-player-only : onWaveformReady');
        },

        /**
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
        <video ref="audioPlayer" class="video-js vjs-play-skin"></video>
    </div>
</template>

<style lang="scss" scoped>

.vjs-play-skin::v-deep {
  .vjs-control-bar {
      height: 5em;
  }
  .vjs-control {
      width: 5em;
  }
  .vjs-volume-bar {
      margin: 2.4em 0.45em;
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
    line-height: 3.5em;
  }
  .vjs-big-play-button {
    background-color: black;
    border: 2px solid white;
    border-radius: 0;
    top: 50%;
    left: 50%;
    margin-left: -45px;
    margin-top: -24px;
  }
  .vjs-button > .vjs-icon-placeholder::before {
    color: white;
    font-size: 4em;
    line-height: 1.25;
  }
  .vjs-progress-holder,
  .vjs-play-progress,
  .vjs-slider-bar,
  .vjs-volume-panel .vjs-volume-control,
  .vjs-big-play-button .vjs-icon-placeholder::before {
      color: white;
  }
  .vjs-volume-level,
  .vjs-slider-horizontal.vjs-play-progress,
  .vjs-slider-bar {
      background: white;
  }
}

</style>
