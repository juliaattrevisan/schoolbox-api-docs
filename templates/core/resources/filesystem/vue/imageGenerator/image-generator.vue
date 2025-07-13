<script lang="ts" setup>
/**
 * AI Image Generator used inside the resource picker modal
 */
import IgPrompt from "./ig-prompt.vue";
import igFeed from "./ig-feed.vue";
</script>

<template>
    <h2>Generate an Image</h2>
    <p>
        A good prompt will contain information on the format, style, subject, mood, light, colours, artistic style and
        framing. See more information on these on
        <!-- TODO: Add help link -->
        <a href="/help/sso/send?returnUrl=TBC" target="_blank">Schoolbox Help</a>
    </p>
    <ig-feed />
    <ig-prompt />
</template>

<style scoped lang="scss">
h2 {
    /* override _dropzone.scss [.reveal-modal.file-picker h2] */
    margin: -1rem 0 1rem 0 !important;
}
</style>
