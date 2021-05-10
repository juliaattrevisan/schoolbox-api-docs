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
         * the waveform has been put into the video component,
         * which is put at the top of the container, so need to re-apply some 
         * divs to still behave the same as a standard video
         *
         */
        onWaveformReady () {
            const audioContainer = this.$refs.audioContainer.firstChild;
            this.addPlayToggle(audioContainer);
            this.updateBigPlayButton(audioContainer);
            this.updateControlBar(audioContainer);
        },

        /** 
         * Put a toggle play/pause over the top of the waveform
         *
         */
        addPlayToggle (audioContainer) {
            const bigPlayButton = audioContainer.querySelector('.vjs-big-play-button');
            const playToggle = document.createElement('div'); //cover the entire video container
            playToggle.style.position = 'absolute';
            playToggle.style.left = '0';
            playToggle.style.top = '0';
            playToggle.style.width = '100%';
            playToggle.style.height = '100%';
            playToggle.addEventListener('click', () => {
                if (this.player.paused()) {
                    this.player.play();
                    bigPlayButton.classList.add('vjs-hidden');
                } else {
                    this.player.pause();
                }
            });
            audioContainer.appendChild(playToggle);  
        },

        /** 
         * Move the big play button above the waveform and ensure behaves as expected
         *
         */
        updateBigPlayButton (audioContainer) {
            const bigPlayButton = audioContainer.querySelector('.vjs-big-play-button');
            bigPlayButton.classList.remove('vjs-hidden');
            bigPlayButton.addEventListener('click', () => {
                bigPlayButton.classList.add('vjs-hidden');
            });
            audioContainer.appendChild(bigPlayButton);
        },

        /** 
         * Put the control above the waveform
         *
         */
        updateControlBar (audioContainer) {
            const controlBar = audioContainer.querySelector('.vjs-control-bar');
            audioContainer.appendChild(controlBar);   
        }

    }

}
</script>

<template>
    <div ref="audioContainer">
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
    display: block;
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
