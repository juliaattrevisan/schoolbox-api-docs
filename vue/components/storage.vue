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
                this.player = new this.MediaElementPlayer(this.$el, {
                    // If 'stretching' is set to 'auto', the video will not resize as the window resizes.
                    // If 'stretching' is set to 'responsive', we cannot enforce our desired maximum height, as the
                    // aspect ratio of the container will always match the aspect ratio of the video.  This is a shame,
                    // because if we used the 'responsive' mode, we wouldn't have to set up a 'resize' event handler,
                    // and resizing would be less janky.
                    //    The reason we want to enforce a maximum height is that if we did not do this, videos recorded
                    // in portrait aspect ratio would take up lots of vertical space, and even be too tall for some
                    // small screens.
                    //    To get the resizing and container aspect ratio to work as we desire, we must set 'stretching'
                    // to 'none', and create our own 'resize' event handler.
                    // Note that with 'stretching' set to 'none', unless the height of the container is explicitly set
                    // (as it is set below), the height of the video will end up as zero.
                    // See: https://github.com/mediaelement/mediaelement/blob/master/docs/api.md
                    stretching: "none",
                });

                let container = this.$el.closest(".mejs__container");
                if (container) {
                    const $container = $(container);
                    const containerMaxH = 385;
                    $container.css("height", containerMaxH + "px");
                    const containerH = $container.height();
                    const containerW = $container.width();

                    // Ensure the video poster fills the container with the correct aspect ratio, and has black bars
                    // added on the top/bottom or sides if necessary instead of having a hidden part overflow the
                    // bounds of the container.
                    $(container).find(".mejs__poster").attr("style", "");

                    // Ensure the video dimensions match the container dimensions when the video is playing.
                    // Documentation: https://github.com/mediaelement/mediaelement/blob/master/docs/usage.md
                    this.player.setPlayerSize(containerW, containerH);

                    // Ensure the video container adjusts in size correctly as the window size changes (both when it
                    // is playing and not playing).
                    // If this function is removed (with the MediaElementPlayer 'stretching' option remaining as 'none'):
                    //  * downsizing the window from a large size will cause a horizontal scrollbar to appear on the
                    //    video container
                    //  * when upsizing the window from a small size, the video will remain small as the surrounding
                    //    space grows
                    //  * The video content size will not change as the window grows/shrinks.
                    const closurePlayer = this.player;
                    const aspectRatio = containerW / containerH;
                    $(window).resize(function () {
                        $container.css("width", "auto");
                        const containerW = $container.width();
                        let containerH = Math.round(containerW / aspectRatio);

                        if (containerH > containerMaxH) {
                            containerH = containerMaxH;
                        }

                        $container.css("height", containerH + "px");
                        closurePlayer.setPlayerSize(containerW, containerH);
                    });
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
