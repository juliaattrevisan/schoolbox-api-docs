<script lang="ts" setup>
/**
 * A basic file drop that is styled like a normal resource attach dropzone
 * This currently only supports a single file at a time, but could be extended to support multiple files
 */

import { useFileDialog } from "@vueuse/core";
import FileDropPreview from "./file-drop-preview.vue";
import type { FileDropFile } from "./fileDropTypes";

interface Props {
    id: string;
    placeholder?: string;
    accept?: string;
    /**
     * Handler for the File object when a file is selected
     * @param file - The file object
     * @returns Whether the file should be considered as valid or not, return a string to specify an error message
     */
    fileHandler?: (file: File) => Promise<boolean | string> | boolean | string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: "Click or drag here to attach files",
    accept: "*/*",
    fileHandler: () => true,
});

const currentFile = defineModel<FileDropFile | null>("currentFile", { required: true });

const { reset, open, onChange, onCancel } = useFileDialog({
    accept: props.accept,
    multiple: false,
});

const importFileName = window.Vue.ref<string | null>(null);

/**
 * When the file import is cancelled
 */
function handleCancel(): void {
    currentFile.value = null;
}

/**
 * When the file import is clicked
 */
function handleClick(): void {
    reset();
    open();
}

/**
 * When the File is loaded
 * @param file - The file object
 */
async function handleLoad(file: FileDropFile): Promise<void> {
    const result = await props.fileHandler(file.file);

    file.error = false;
    if (result !== true) {
        file.error = result || "Invalid file";
    }

    currentFile.value = file;
}

/**
 * Handle a list of files
 * @param files - The relevant files
 */
function handleFiles(files: FileList | null): void {
    if (files === null || files.length === 0) {
        handleCancel();
        importFileName.value = null;
        return;
    }

    const file = { file: files[0], error: false } as const;

    // Is the file the right type?
    if (!window.Dropzone.isValidFile(file.file, props.accept)) {
        currentFile.value = {
            ...file,
            error: "You can't upload files of this type.",
        };
        return;
    }

    void handleLoad(file);
}

onChange(handleFiles);
onCancel(handleCancel);

const isOverDropZone = window.Vue.ref(false);

/**
 * When the user drags over the drop zone
 * @param event - The drag event
 */
function handleDragOver(event: DragEvent): void {
    event.preventDefault();
    // Is the user dragging a file?
    if (event.dataTransfer && event.dataTransfer.types.includes("Files")) {
        isOverDropZone.value = true;
    }
}

/**
 * When the user drops a file on the drop zone
 * @param event - The drop event
 */
function handleDrop(event: DragEvent): void {
    event.preventDefault();
    isOverDropZone.value = false;

    if (!event.dataTransfer) return;
    handleFiles(event.dataTransfer.files);
}
</script>

<template>
    <div v-if="currentFile" class="ab-upload ab-upload-panel l-mar-m--bottom">
        <div class="dzone dz-clickable dz-started" style="border: 0">
            <file-drop-preview :file="currentFile" @remove="currentFile = null" />
        </div>
    </div>

    <label
        v-else
        :for="props.id"
        class="attachzone s64"
        :class="{ 'dz-drag-hover': isOverDropZone }"
        @click.prevent="handleClick"
        @drop.prevent="handleDrop"
        @dragover="handleDragOver"
        @dragleave="isOverDropZone = false"
        @dragend="isOverDropZone = false"
    >
        <input :id="props.id" type="file" :accept="props.accept" @change="console.log('File selected')" />
        <div class="dzone icon-attach">
            <span>{{ props.placeholder }}</span>
        </div>
    </label>
</template>

<style scoped lang="scss">
label.attachzone {
    font-weight: normal;

    input {
        display: none;
    }
}
</style>
