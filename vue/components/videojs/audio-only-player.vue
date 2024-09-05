<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/*
 * To play an audio only, which contains waveform visualizer
 *
 */

import "video.js/dist/video-js.css";
import "videojs-wavesurfer/dist/css/videojs.wavesurfer.css";
import videojs from "video.js";
import { timeout } from "@js/util/jsUtils";

export default {
    props: {
        options: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },
    },

    data() {
        return {
            isVideoEncoding: true, //until it's checked at least once or confirmed the video is a blob
            processingSrc: `${window.cdnPrefix}/images/core/video-processing.gif`, // if the audio is still processing, show a processing gif
        };
    },

    created() {
        this.hasHash = this.checkForHash();
    },

    mounted() {
        if (this.hasHash) {
            this.checkVideoEncodingLoop();
        } else {
            this.isVideoEncoding = false; //no need to be checking a blob file
            this.initVideojs();
        }
    },

    beforeUnmount() {
        if (this.player) {
            this.player.dispose(); //cleanup, can be re-activated later on the same page
        }
    },

    methods: {
        /**
         * if the file contains a hash, we should ensure the video isn't transcoding prior to displaying
         *
         * @returns { Boolean }
         */
        checkForHash() {
            this.hash = this.options.audioPlayerHash;
            if (this.hash) {
                //need to check its status first
                return true;
            } else {
                //otherwise it's a blob recording
                return false;
            }
        },

        /**
         * ensure the video has finished processing prior to displaying
         *
         */
        async checkVideoEncodingLoop() {
            this.isVideoEncoding = await this.checkIsVideoEncoding();
            if (this.isVideoEncoding && document.body.contains(this.$el)) {
                //ensure that the player is still in the DOM (could have been deleted)
                await timeout(2000); //loop around until it's complete
                this.checkVideoEncodingLoop();
            } else if (!this.isVideoEncoding && document.body.contains(this.$el)) {
                this.initVideojs();
            }
        },

        /**
         * check whether SB has encoded the video yet
         *
         * @returns { Boolean }
         */
        async checkIsVideoEncoding() {
            try {
                const response = await fetch(
                    "/storage/checkVideoStatus.php?" +
                        new URLSearchParams({
                            hash: this.hash,
                        }),
                );
                const responseData = await response.json();
                const videoStatus = responseData.status;
                if (videoStatus === "pending" || videoStatus === "processing") {
                    return true;
                } else {
                    return false;
                }
            } catch (error) {
                console.warn(error);
            }
        },

        /**
         * set up videojs
         *
         */
        initVideojs() {
            this.$nextTick(() => {
                this.player = videojs(this.$refs.audioPlayer, this.options, this.onVideojsReady);
                this.player.on("play", this.onPlay);
                this.player.on("waveReady", this.onWaveformReady);
            });
        },

        /**
         * videojs is ready, dispatch event in case anyone listening and pass in the audio src
         *
         */
        onVideojsReady() {
            this.player.src({
                src: this.options.audioPlayerSrc,
                type: this.options.audioPlayerType,
            });
            this.$emit(
                "on-dimensions-ready",
                this.$refs.audioContainer.offsetWidth,
                this.$refs.audioContainer.offsetHeight,
            );
        },

        /**
         * ensure big play button is disabled on play
         *
         */
        onPlay() {
            const audioContainer = this.$refs.audioContainer.firstChild;
            audioContainer.querySelector(".vjs-big-play-button").classList.add("vjs-hidden");
        },

        /**
         * the waveform has been put into the video component,
         * which is put at the top of the container, so need to re-apply some
         * divs to still behave the same as a standard video
         *
         */
        onWaveformReady() {
            const audioContainer = this.$refs.audioContainer.firstChild;
            this.addPlayToggle(audioContainer);
            this.updateBigPlayButton(audioContainer);
            this.updateControlBar(audioContainer);
        },

        /**
         * Put a toggle play/pause over the top of the waveform
         *
         */
        addPlayToggle(audioContainer) {
            const bigPlayButton = audioContainer.querySelector(".vjs-big-play-button");
            const playToggle = document.createElement("div"); //cover the entire video container
            playToggle.style.position = "absolute";
            playToggle.style.left = "0";
            playToggle.style.top = "0";
            playToggle.style.width = "100%";
            playToggle.style.height = "100%";
            playToggle.addEventListener("click", () => {
                if (this.player.paused()) {
                    this.player.play();
                    bigPlayButton.classList.add("vjs-hidden");
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
        updateBigPlayButton(audioContainer) {
            const bigPlayButton = audioContainer.querySelector(".vjs-big-play-button");
            bigPlayButton.classList.remove("vjs-hidden");
            bigPlayButton.addEventListener("click", () => {
                bigPlayButton.classList.add("vjs-hidden");
            });
            audioContainer.appendChild(bigPlayButton);
        },

        /**
         * Put the control above the waveform
         *
         */
        updateControlBar(audioContainer) {
            const controlBar = audioContainer.querySelector(".vjs-control-bar");
            audioContainer.appendChild(controlBar);
        },
    },
};
</script>

<template>
    <div ref="audioContainer">
        <img v-if="isVideoEncoding" class="processing-img" :src="processingSrc" alt="Audio is processing" />
        <video v-else ref="audioPlayer" class="video-js vjs-play-skin"></video>
    </div>
</template>

<style lang="scss" scoped>
.processing-img {
    width: 100%;
    height: auto;
}

:deep(.vjs-play-skin) {
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
        font-size: 0.875rem;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        width: auto;
        min-width: 0;
        line-height: 3.5em;
    }
    .vjs-big-play-button {
        display: block;
        position: absolute;
        z-index: calc(var(--sbx-depth-default) + 1);
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
    @media screen and (max-width: 640px) {
        //shrink down everything to fit better
        .vjs-big-play-button {
            top: 40%;
            transform: scale(0.8);
        }
        .vjs-control-bar {
            height: 2.5em;
            overflow: hidden;
        }
        .vjs-control {
            width: 2.5em;
        }
        .vjs-time-control {
            font-size: 0.7rem;
            padding-left: 0.1rem;
            padding-right: 0.1rem;
            line-height: 2.2em;
        }
        .vjs-button > .vjs-icon-placeholder::before {
            font-size: 2em;
            line-height: 1.1;
        }
    }
}
</style>
