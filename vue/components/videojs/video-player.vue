<script>

/*
 * To play a video
 *
 */

import 'video.js/dist/video-js.css';
import videojs from 'video.js';
import { gsap } from 'gsap/all';


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
            this.player = videojs(this.$refs.videoPlayer, this.options);
            this.player.on('loadeddata', this.onVideoDataLoaded);
            this.player.on('error', this.onVideoError);
            gsap.from(this.$refs.videoPlayer, 0.5, { opacity: 0, scale: 1.025 } ); 
        },

        /** 
         * first frame of video is loaded, so can pass details through to anyone listening
         *
         */
        onVideoDataLoaded () {
            this.$emit('on-video-data-loaded', this.$refs.videoPlayer);
        },

        /** 
         * unable to load for whatever reason
         *
         */
        onVideoError () {
            this.$emit('on-video-error');
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
        <video ref="videoPlayer" class="video-js vjs-play-skin"></video>
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
    background: rgba(43, 51, 63, 0.7);
    border: 2px solid white;
    border-radius: 0;
    top: 50%;
    left: 50%;
    margin-left: -45px;
    margin-top: -24px;
    transition: 0.2s opacity;
    &:hover {
        opacity: 0.8;
    }
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
