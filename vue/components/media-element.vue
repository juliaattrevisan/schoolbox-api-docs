<script>
/**
 * Media element: wrap the given storage object in a MediaElement instance.
 */

export default {
    name: "media-element",
    props: {
        storage: {
            type: Object,
            validator: (value) => value.hash && (value.type === 'video' || value.type === 'audio')
        },
    },
    computed: {
        jQuery: () => window.jQuery,
    },
    mounted() {
        this.jQuery(this.$el).mediaelementplayer();
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
    <span v-else></span>
</template>
