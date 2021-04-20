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
            default() {
                return {};
            }
        }
    },

    data () {
        return {
            player: null
        }
    },

    mounted () {
        this.player = videojs(this.$refs.videoPlayer, this.options, () => {
            console.log('onPlayerReady', this.player);
        })
    },

    beforeDestroy () {
        if (this.player) {
            this.player.dispose()
        }
    }

}
</script>

<template>
    <div class="yep">
        <video ref="videoPlayer" class="video-js"></video>
    </div>
</template>