<script>
/**
 * Render the given storage object appropriately.
 *
 * * Audio and video files have a MediaElement player attached
 * * Images are rendered in a `<img>` tag
 * * Other files are rendered with an `<a>` tag, providing a link for download
 */

import filesize from "filesize";

export default {
    name: "storage",
    props: {
        storage: {
            type: Object,
            default: {},
        },
        filename: {
            type: String,
            default: '',
        },
    },
    computed: {
        MediaElementPlayer: () => window.MediaElementPlayer,
    },
    data() {
        return {
            videoWidth: null,
            videoHeight: null,
            player: null,
        }
    },
    mounted() {
        switch (this.storage.type) {
            case 'audio':
                this.player = new this.MediaElementPlayer(this.$el);
                break;

            case 'video':
                this.player = new this.MediaElementPlayer(this.$el);

                // Work around what I think is an issue with mediaelement not
                // knowing what to do when the player loads.
                // The video isn't set to the same size as the player.
                let container = this.$el.closest('.mejs__container');
                if (container) {
                    this.videoWidth  = container.clientWidth;
                    this.videoHeight = container.clientHeight;
                }
                break;

            default:
                break;
        }
    },
    beforeDestroy() {
        switch (this.storage.type) {
            case 'audio':
            case 'video':
                if (this.player) {
                    if (!this.player.paused) {
                        this.player.pause();
                    }
                    this.player.remove();
                    this.player = null;
                }
                break;
        }
    },
    filters: {
        filesize(size) {
            // This uses the JEDEC standard (1KB = 1024B),
            // rather than the SI standard (1KB = 1000B, 1KiB = 1024B):
            // Schoolbox's file size rendering uses the JEDEC standard for
            // better or worse.
            return filesize(size);
        }
    }
}
</script>

<template>
    <video v-if="storage.type === 'video'"
           controls
           preload="none"
           :poster="storage._links.poster"
           :width="videoWidth"
           :height="videoHeight"
    >
        <source type="video/mp4" :src="storage._links.stream">
    </video>
    <audio v-else-if="storage.type === 'audio'" controls preload="none">
        <source type="audio/mpeg" :src="storage._links.stream">
    </audio>
    <img v-else-if="storage.type === 'image'" :src="storage._links.image" :alt="filename" />
    <a v-else :href="storage._links.fetch">
        <img :src="storage._links.icon" :alt="filename" /> {{ filename }}
        <span class="meta">({{ storage.size | filesize }})</span>
    </a>
</template>
