<script lang="ts" setup>
/**
 * The component actions at the bottom of the image generator modal
 */

import { uploadFileToStorage } from "@js/resource/resourceUtils";
import { useImageGeneratorStore } from "./store/imageGeneratorStore";

const igStore = useImageGeneratorStore();

/**
 * When the cancel button is clicked
 */
function closeModal(): void {
    if (!window.top) {
        // idk why you'd get here, but if you do, just throw an error
        throw new Error("window.top is not available");
    }

    window.top.postMessage(JSON.stringify({ data: [] }), window.location.origin);
}

/**
 * Upload the image to storage and trigger whatever opened this modal to learn about it (ckeditor or normal attachzone)
 */
async function uploadImage(): Promise<void> {
    if (!window.top) {
        // idk why you'd get here, but if you do, just throw an error
        throw new Error("window.top is not available");
    }

    const selectedFeedItem = igStore.feed.find((item) => item.entryKey === igStore.selectedFeedEntry);
    if (!selectedFeedItem) {
        throw new Error("Unable to find selected feed item");
    }

    if (!("image" in selectedFeedItem) || selectedFeedItem.image === null) {
        throw new Error("Selected feed item does not have a valid image");
    }

    igStore.uploadLoading = true;
    igStore.uploadError = null;

    const bstr = atob(selectedFeedItem.image.b64);
    const bytes = new Uint8Array(bstr.length);
    for (let i = 0; i < bstr.length; i += 1) {
        bytes[i] = bstr.charCodeAt(i);
    }

    const file = new File([bytes], selectedFeedItem.image.name, { type: selectedFeedItem.image.mime });
    const resource = await uploadFileToStorage(file);

    igStore.uploadLoading = false;
    if (resource.success === false) {
        igStore.uploadError = resource.message;
        return;
    }

    const data = {
        title: file.name,
        mimeType: file.type,
        filesize: file.size,
        hash: resource.data.hash,
    };
    window.top.postMessage(JSON.stringify({ data: [data] }), window.location.origin);
}
</script>

<template>
    <div class="component-action">
        <section>
            <button
                class="button"
                :disabled="igStore.selectedFeedEntry === null || igStore.uploadLoading"
                @click="uploadImage"
            >
                Upload
            </button>
            <button class="button cancel" role="button" @click="closeModal">Cancel</button>
        </section>
    </div>
</template>

<style scoped lang="scss"></style>
