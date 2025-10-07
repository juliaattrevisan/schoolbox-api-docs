<script setup lang="ts">
/**
 * Show a grid of storage files with previews on click
 */
import { humanFileSize } from "@js/util/fileUtils";
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

/**
 * Open an image lightbox for the given image
 * @param file The file to open
 */
async function openImageLightbox(file: FileWithName): Promise<void> {
    const { slides, selectedIndex } = getStorageGallerySlides(
        props.files.map((f) => f.storage),
        file.storage.hash,
    );

    const lightbox = await createLightbox(`img-gallery-${props.id}`, slides, {
        index: selectedIndex,
    });
    lightbox.openGallery();
}

/**
 * Get the URL to use as an icon for a given file
 * @param file The file to get the icon for
 * @returns url
 */
function getFileIcon(file: StorageFile): string {
    if (file._links.image) {
        return `${file._links.image}&size=square64`;
    }

    if (file._links.icon) {
        return file._links.icon;
    }

    return `/ajax/mime/${file.type}`;
}
</script>

<template>
    <ul
        v-if="props.files.length > 0"
        class="grid small-block-grid-1 medium-block-grid-2"
        :data-test="`files-grid-${props.id}`"
    >
        <li v-for="file in props.files" :key="file.storage.hash" class="actions-small-1">
            <div class="list-item">
                <div class="card attachment small-12">
                    <a
                        :href="file.storage._links.fetch"
                        :title="file.filename || file.storage.name || undefined"
                        @click.prevent="() => openImageLightbox(file)"
                    >
                        <img :src="getFileIcon(file.storage)" alt="Download" />
                        <p>{{ file.filename || file.storage.name }}</p>
                        <p v-if="file.storage.size !== null" class="meta">{{ humanFileSize(file.storage.size) }}</p>
                    </a>
                </div>
            </div>
            <nav>
                <a :href="`${file.storage._links.fetch}&save=1`" title="Download" class="icon-download"></a>
            </nav>
        </li>
    </ul>
</template>

<style scoped></style>
