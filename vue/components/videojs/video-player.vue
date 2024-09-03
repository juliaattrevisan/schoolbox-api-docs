<script>
/*
 * To play a video
 *
 */

import "video.js/dist/video-js.css";
import videojs from "video.js";
import { gsap } from "gsap/all";
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
            videoHeight: 0, //to ensure the parent container is high enough when the video is loaded
            isVideoEncoding: true, //until it's checked at least once or confirmed the video is a blob
            processingSrc: `${window.cdnPrefix}/images/core/video-processing.gif`, // if the video is still processing, show a processing gif
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
            this.hash = this.options?.sources[0].hash;
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
                this.videoHeight = this.$refs.videoPlayer.offsetHeight;

                this.player = videojs(this.$refs.videoPlayer, this.options, this.onVideoPlayerReady);
                this.player.on("error", this.onVideoError);
                gsap.from(this.$refs.videoPlayer, 0.5, { opacity: 0, scale: 1.025 });
            });
        },

        /**
         * there's no fully cross-browser accurate load detection,
         * and as all we need is height detection, just use an interval
         *
         */
        onVideoPlayerReady() {
            this.checkInterval = setInterval(this.checkVideoHeight, 500);
        },

        /**
         * there's no fully cross-browser accurate load detection, and as all we need is height detection,
         * just use an interval. This mainly relates to safari ios, which doesn't load a input captured video.
         *
         */
        checkVideoHeight() {
            const currentVideoHeight = this.$refs.container.firstChild.offsetHeight; //videojs wraps around the <video> element
            if (this.videoHeight !== currentVideoHeight) {
                this.videoHeight = currentVideoHeight;
                clearInterval(this.checkInterval);
                this.$emit("on-dimensions-ready", this.$refs.container.firstChild.offsetWidth, currentVideoHeight);
            }
        },

        /**
         * unable to load for whatever reason
         *
         */
        onVideoError() {
            this.$emit("on-video-error");
        },
    },
};
</script>

<template>
    <div ref="container">
        <img v-if="isVideoEncoding" class="processing-img" :src="processingSrc" alt="Video is processing" />
        <video v-else ref="videoPlayer" class="video-js vjs-play-skin"></video>
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
        background: rgba(43, 51, 63, 0.85);
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
    @media screen and (max-width: 640px) {
        //shrink down everything to fit better
        .vjs-big-play-button {
            top: 40%;
            transform: scale(0.8);
        }
    }
}
</style>
