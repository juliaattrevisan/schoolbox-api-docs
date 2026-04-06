<script setup lang="ts">
/**
 * Show a grid of storage files with previews on click
 */
import { humanFileSize } from "@js/util/fileUtils";
import { openFilePreview } from "@js/preview/previewUtils";
import { getFileIcon } from "./resourceUtils";
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
 * When the user clicks on a file
 * @param file The file that was clicked
 * @param event The click event
 */
async function onFileClick(file: FileWithName, event: MouseEvent): Promise<void> {
    const href = file.storage._links.fetch;
    if (!href) {
        return; // no fetch link, can't preview
    }

    if (event.ctrlKey || event.shiftKey || event.metaKey) {
        return; // the user is trying to open in a new tab/window, don't open the preview
    }

    event.preventDefault();
    await openFilePreview(href);
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
                        @click="(event) => onFileClick(file, event)"
                    >
                        <img :src="getFileIcon(file.storage)" alt="Download" />
                        <p>{{ file.filename || file.storage.name }}</p>
                        <p v-if="file.storage.size !== null" class="meta">{{ humanFileSize(file.storage.size) }}</p>
                    </a>
                </div>
            </div>
            <nav>
                <a :href="`${file.storage._links.fetch}&save=1`" title="Download"
                    ><sbx-icon name="download"></sbx-icon
                ></a>
            </nav>
        </li>
    </ul>
</template>

<style scoped></style>
