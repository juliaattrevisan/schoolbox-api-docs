<script lang="ts" setup>
/**
 * Render the given storage object appropriately.
 *
 * Audio and video files have a MediaElement player attached
 * Images are rendered in a `<img>` tag
 * Other files are rendered with an `<a>` tag, providing a link for download
 */

import { humanFileSize } from "@js/util/fileUtils";
import { getConfigForVideo } from "@js/mediaelementplayer/mepUtils";
import type { MediaElementPlayer } from "@js/mediaelementplayer/mepUtils";
import { ResourceType, type StorageFile } from "@js/resource/resourceUtils";

interface Props {
    storage: StorageFile;
    filename: string;
}

const props = defineProps<Props>();
const playerEl = window.Vue.useTemplateRef<HTMLElement>("playerRef");

const player = window.Vue.ref<MediaElementPlayer | null>(null);

window.Vue.onMounted(() => {
    if (playerEl.value === null) {
        // shouldn't get here, console.error rather than throw to avoid breaking other functionality
        console.error("Unable to initialise MediaElement player, playerEl is null");
        return;
    }

    // We need to wait for the source element to be visible first before initialising the MediaElement player
    // otherwise it will never render correctly (see SA-18855)
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!playerEl.value) {
                console.error("Unable to initialise MediaElement player, playerEl is null");
                return;
            }

            if (entry.isIntersecting) {
                // The component is visible, ready to init the player
                switch (props.storage.type) {
                    case ResourceType.AUDIO:
                        player.value = new window.MediaElementPlayer(playerEl.value, {
                            // Override the default iconSprite reference and leave blank to avoid load error
                            // CSS is used to display the icons instead
                            // refer to https://github.com/mediaelement/mediaelement/blob/master/docs/api.md#configuration
                            iconSprite: ``,
                        });
                        break;

                    case ResourceType.VIDEO:
                        player.value = new window.MediaElementPlayer(playerEl.value, getConfigForVideo());
                        break;

                    default:
                        break;
                }

                // Disconnect the observer after we've initialised the player
                observer.disconnect();
            }
        });
    });
    observer.observe(playerEl.value);
});

window.Vue.onBeforeUnmount(() => {
    if (props.storage.type === ResourceType.VIDEO && player.value) {
        if (!player.value.paused) {
            player.value.pause();
        }
        player.value.remove();
        player.value = null;
    }
});

/**
 * This uses the JEDEC standard (1KB = 1024B),
 * rather than the SI standard (1KB = 1000B, 1KiB = 1024B):
 * Schoolbox's file size rendering uses the JEDEC standard for
 * better or worse.
 * @param size - The size in bytes
 * @returns A human-readable file size string
 */
function filesize(size: number | null): string {
    if (size === null) {
        return "0 B";
    }
    return humanFileSize(size);
}
</script>

<template>
    <video
        v-if="storage.type === ResourceType.VIDEO"
        ref="playerRef"
        controls
        preload="none"
        style="width: 100%; height: 100%"
        :poster="storage._links.poster"
    >
        <source type="video/mp4" :src="storage._links.stream" />
    </video>
    <audio
        v-else-if="storage.type === 'audio'"
        ref="playerRef"
        controls
        preload="none"
        style="width: 100%; height: 100%"
    >
        <source type="audio/mpeg" :src="storage._links.stream" />
    </audio>
    <img v-else-if="storage.type === 'image'" ref="playerRef" :src="storage._links.image" :alt="filename" />
    <a v-else ref="playerRef" :href="storage._links.fetch">
        <img :src="storage._links.icon" :alt="filename" /> {{ filename }}
        <span class="meta">({{ filesize(storage.size) }})</span>
    </a>
</template>
