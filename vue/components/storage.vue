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
            default: "",
        },
    },
    computed: {
        MediaElementPlayer: () => window.MediaElementPlayer,
    },
    data() {
        return {
            player: null,
        };
    },
    mounted() {
        switch (this.storage.type) {
            case "audio":
                this.player = new this.MediaElementPlayer(this.$el);
                break;

            case "video":
                this.player = new this.MediaElementPlayer(this.$el);

                let container = this.$el.closest(".mejs__container");
                if (container) {
                    // The two lines below:
                    //  * Ensure the video dimensions match the container dimensions both when the video is initially
                    //    displayed and when it is playing.
                    //  * Ensure the video poster fills the container with the correct aspect ratio, and has black bars
                    //    added on the top/bottom or sides if necessary instead of having a hidden part overflow the
                    //    bounds of the container.
                    // Documentation: https://github.com/mediaelement/mediaelement/blob/master/docs/usage.md
                    this.player.setPlayerSize($(container).width(), $(container).height());
                    $(container).find(".mejs__poster").attr("style", "");
                }
                break;

            default:
                break;
        }
    },
    beforeUnmount() {
        switch (this.storage.type) {
            case "audio":
            case "video":
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
    methods: {
        filesize(size) {
            // This uses the JEDEC standard (1KB = 1024B),
            // rather than the SI standard (1KB = 1000B, 1KiB = 1024B):
            // Schoolbox's file size rendering uses the JEDEC standard for
            // better or worse.
            return filesize(size);
        },
    },
};
</script>

<template>
    <video
        v-if="storage.type === 'video'"
        controls
        preload="none"
        style="width: 100%; height: 100%"
        :poster="storage._links.poster"
    >
        <source type="video/mp4" :src="storage._links.stream" />
    </video>
    <audio v-else-if="storage.type === 'audio'" controls preload="none" style="width: 100%; height: 100%">
        <source type="audio/mpeg" :src="storage._links.stream" />
    </audio>
    <img v-else-if="storage.type === 'image'" :src="storage._links.image" :alt="filename" />
    <a v-else :href="storage._links.fetch">
        <img :src="storage._links.icon" :alt="filename" /> {{ filename }}
        <span class="meta">({{ filesize(storage.size) }})</span>
    </a>
</template>
