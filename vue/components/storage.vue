<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Render the given storage object appropriately.
 *
 * * Audio and video files have a MediaElement player attached
 * * Images are rendered in a `<img>` tag
 * * Other files are rendered with an `<a>` tag, providing a link for download
 */

import { humanFileSize } from "@js/util/fileUtils";
import { getConfigForVideo } from "@js/mediaelementplayer/mepUtils.ts";

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
        // We need to wait for the source element to be visible first before initialising the MediaElement player
        // otherwise it will never render correctly (see SA-18855)
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    // The component is visible, ready to init the player
                    switch (this.storage.type) {
                        case "audio":
                            this.player = new this.MediaElementPlayer(this.$el);
                            break;

                        case "video":
                            this.player = new this.MediaElementPlayer(this.$el, getConfigForVideo());
                            break;

                        default:
                            break;
                    }

                    // Disconnect the observer after we've initialised the player
                    observer.disconnect();
                }
            });
        });
        observer.observe(this.$el);
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
            return humanFileSize(size);
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
