<script>
/**
 * Render the given storage object appropriately.
 *
 * * Audio and video files have a MediaElement player attached
 * * Images are rendered in a `<img>` tag
 * * Other files are rendered with an `<a>` tag, providing a link for download
 */

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
        jQuery: () => window.jQuery,
    },
    mounted() {
        switch (this.storage.type) {
            case 'audio':
            case 'video':
                this.jQuery(this.$el).mediaelementplayer();
                break;

            default:
                break;
        }
    },
}
</script>

<template>
    <video v-if="storage.type === 'video'" controls preload="none" :poster="storage._links.poster">
        <source type="video/mp4" :src="storage._links.stream">
    </video>
    <audio v-else-if="storage.type === 'audio'" controls preload="none">
        <source type="audio/mpeg" :src="storage._links.stream">
    </audio>
    <img v-else-if="storage.type === 'image'" :src="storage._links.image" :alt="filename" />
    <a v-else :href="storage._links.fetch">
        <img :src="storage._links.icon" :alt="filename" /> {{ filename }}
    </a>
</template>
