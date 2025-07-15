<script lang="ts" setup>
/**
 * The component actions at the bottom of the image generator modal
 */

import { useImageGeneratorStore } from "./store/imageGeneratorStore";

const igStore = useImageGeneratorStore();

/**
 * When the upload button is clicked
 */
function onUploadClick(): void {
    // eslint-disable-next-line no-alert -- TODO: Implement this, currently just a placeholder
    alert("Unimplemented, see SA-20261");
}

/**
 * When the cancel button is clicked
 */
function onCancelClick(): void {
    if (!window.top) {
        // idk why you'd get here, but if you do, just throw an error
        throw new Error("window.top is not available");
    }

    // $(".attachzone").data("plugin_resourceAttach").dropzone.removeAllFiles(true);
    window.top.postMessage(JSON.stringify({ data: [] }), window.location.origin);
}
</script>

<template>
    <div class="component-action">
        <section>
            <button class="button" :disabled="igStore.selectedFeedEntry === null" @click="onUploadClick">Upload</button>
            <button class="button" @click="onCancelClick">Cancel</button>
        </section>
    </div>
</template>

<style scoped lang="scss"></style>
