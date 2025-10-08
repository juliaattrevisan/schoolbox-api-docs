<script setup lang="ts">
/**
 * Show an image gallery with previewable images from a list of storage files
 */
import { createLightbox, getStorageGallerySlides } from "@js/image-lightbox/imageLightboxEvent";
import type { StorageFile } from "./resourceUtils";

type GalleryFile = {
    storage: StorageFile;
    filename?: string;
    hidden?: boolean; // whether to hide this file from the gallery but still show it in the lightbox
};

interface Props {
    id: string; // a unique ID to identify this image gallery
    files: GalleryFile[];
}

const props = defineProps<Props>();

/** Files (all images) to show in the gallery or lightbox */
const availableFiles = window.Vue.computed(() => {
    return props.files.filter((f) => f.storage._links.image);
});

/** Files (not hidden) to show in the gallery */
const galleryFiles = window.Vue.computed(() => {
    return availableFiles.value.filter((f) => !f.hidden);
});

/**
 * Open an image lightbox for the given image
 * @param file The file to open
 */
async function openImageLightbox(file: GalleryFile): Promise<void> {
    const { slides, selectedIndex } = getStorageGallerySlides(
        availableFiles.value.map((f) => f.storage),
        file.storage.hash,
    );

    const lightbox = await createLightbox(`img-gallery-${props.id}`, slides, {
        index: selectedIndex,
    });
    lightbox.openGallery();
}

defineExpose({ openImageLightbox });
</script>

<template>
    <ul v-if="galleryFiles.length > 0" class="img-gallery" :data-test="`img-gallery-${props.id}`">
        <li v-for="file in galleryFiles" :key="file.storage.hash">
            <a
                :href="`${file.storage._links.image}&size=maxsize`"
                :title="file.filename || file.storage.name || undefined"
                @click.prevent="() => openImageLightbox(file)"
            >
                <img
                    :src="`${file.storage._links.image}&size=square128`"
                    :alt="file.filename || file.storage.name || undefined"
                    style="width: 128px"
                />
            </a>
        </li>
    </ul>
</template>

<style scoped></style>
