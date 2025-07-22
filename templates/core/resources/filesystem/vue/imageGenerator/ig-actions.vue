<script lang="ts" setup>
/**
 * The component actions at the bottom of the image generator modal
 */

import { useImageGeneratorStore } from "./store/imageGeneratorStore";

const igStore = useImageGeneratorStore();

/**
 * When the cancel button is clicked or the image has been uploaded
 */
function closeModal(): void {
    if (!window.top) {
        // idk why you'd get here, but if you do, just throw an error
        throw new Error("window.top is not available");
    }

    window.top.postMessage(JSON.stringify({ data: [] }), window.location.origin);
}

/**
 * When the upload button is clicked
 */
function uploadImage(): void {
    const selectedFeedItem = igStore.feed.find((item) => item.entryKey === igStore.selectedFeedEntry);
    if (!selectedFeedItem) {
        throw new Error("Unable to find selected feed item");
    }

    if (!window.sbProps.activeResourceAttach) {
        throw new Error("No active resource attach modal found");
    }

    if (!("image" in selectedFeedItem) || selectedFeedItem.image === null) {
        throw new Error("Selected feed item does not have a valid image");
    }

    const bstr = atob(selectedFeedItem.image.b64);
    const bytes = new Uint8Array(bstr.length);
    for (let i = 0; i < bstr.length; i += 1) {
        bytes[i] = bstr.charCodeAt(i);
    }

    const file = new File([bytes], selectedFeedItem.image.name, { type: selectedFeedItem.image.mime });
    window.sbProps.activeResourceAttach.dropzone.addFile(file);
    closeModal();
}
</script>

<template>
    <div class="component-action">
        <section>
            <button class="button" :disabled="igStore.selectedFeedEntry === null" @click="uploadImage">Upload</button>
            <button class="button" @click="closeModal">Cancel</button>
        </section>
    </div>
</template>

<style scoped lang="scss"></style>
