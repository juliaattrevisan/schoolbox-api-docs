<script>

/*
 * To play a video
 *
 */

import 'video.js/dist/video-js.css';
import videojs from 'video.js';


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
            this.player = videojs(this.$refs.videoPlayer, this.options, this.onVideojsReady);
        },

         /*
         * videojs is ready, dispatch event in case anyone listening
         *
         */
        onVideojsReady () {
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
        <video ref="videoPlayer" class="video-js vjs-custom-skin"></video>
    </div>
</template>

<style lang="scss">

//DEMO - see https://docs.videojs.com/tutorial-skins.html to update further

.video-js {
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

  .vjs-record-button.vjs-control.vjs-icon-record-start::before,
  .vjs-record-button.vjs-control.vjs-icon-record-stop::before {
    color: #f03;
    &:hover {
      color: #fa1542;
    }
  }
  .vjs-record-button.vjs-control.vjs-icon-record-start::before {
    font-size: 3.5em;
    line-height: .9em;
  }
  .vjs-record-button.vjs-control.vjs-icon-record-stop::before {
    font-size: 5em;
    line-height: 1em;
  }
  .vjs-control:focus::before, .vjs-control:hover::before, .vjs-control:focus {
    text-shadow: none;
  }
}

</style>
