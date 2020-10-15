<script>
/**
 * Media element: wrap the given storage object in a MediaElement instance.
 */

export default {
    name: "media-element",
    props: {
        storage: {
            type: Object,
        },
        filename: {
            type: String,
        },
    },
    computed: {
        jQuery: () => window.jQuery,
    },
    mounted() {
        switch (this.storage.type === 'video') {
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
    <span v-else>
        <a :href="storage._links.fetch">{{ filename }}</a>
    </span>
</template>
