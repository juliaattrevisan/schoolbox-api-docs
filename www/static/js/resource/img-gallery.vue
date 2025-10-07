<script setup lang="ts">
/**
 * Show an image gallery with previewable images from a list of storage files
 */
import { createLightbox, getStorageGallerySlides } from "@js/image-lightbox/imageLightboxEvent";
import type { StorageFile } from "./resourceUtils";

type FileWithName = {
    storage: StorageFile;
    filename?: string;
};

interface Props {
    id: string; // a unique ID to identify this image gallery
    files: FileWithName[];
}

const props = defineProps<Props>();

/** Files (all images) to show in the gallery */
const galleryFiles = window.Vue.computed(() => {
    return props.files.filter((f) => f.storage._links.image);
});

/**
 * Open an image lightbox for the given image
 * @param file The file to open
 */
async function openImageLightbox(file: FileWithName): Promise<void> {
    const { slides, selectedIndex } = getStorageGallerySlides(
        galleryFiles.value.map((f) => f.storage),
        file.storage.hash,
    );

    const lightbox = await createLightbox(`img-gallery-${props.id}`, slides, {
        index: selectedIndex,
    });
    lightbox.openGallery();
}
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
