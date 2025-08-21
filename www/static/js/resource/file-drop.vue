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
    class?: string;
    /**
     * To make this act as a normal form input, specify an inputName
     * This will create a hidden input that a form will include upon submission
     */
    inputName?: string;
    /**
     * The disabled state is intended to be used only when currentFile has a meaningful value
     * It prevents removing of files to let us show a preview as if it were uploaded
     * Currently this will not visually affect the file drop zone if currentFile is null
     */
    disabled?: boolean;
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
    class: "l-mar-s--bottom",
    inputName: undefined,
    disabled: false,
    fileHandler: () => true,
});

const currentFile = defineModel<FileDropFile | null>("currentFile", { default: null });

const { reset, open, onChange, onCancel } = useFileDialog({
    accept: props.accept,
    multiple: false,
});

const hiddenInputRef = window.Vue.ref<HTMLInputElement | null>(null);

/**
 * Keep the hidden input in sync with currentFile
 */
window.Vue.watch(
    currentFile,
    (file) => {
        if (hiddenInputRef.value === null) {
            return;
        }

        // no file? clear it
        if (file === null || file.error !== false) {
            hiddenInputRef.value.value = "";
            hiddenInputRef.value.files = null;
            return;
        }

        // otherwise, set the file
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file.file);
        hiddenInputRef.value.files = dataTransfer.files;
    },
    { immediate: true },
);

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
    <!-- A hidden input so that actual forms can use this -->
    <input
        v-if="props.inputName"
        ref="hiddenInputRef"
        type="file"
        :name="props.inputName"
        :accept="props.accept"
        style="display: none"
    />

    <div v-if="currentFile" class="ab-upload ab-upload-panel" :class="props.class">
        <div class="dzone dz-clickable dz-started" style="border: 0">
            <file-drop-preview :file="currentFile" :disabled="props.disabled" @remove="handleCancel" />
        </div>
    </div>

    <label
        v-else
        :for="props.id"
        class="attachzone s64"
        :class="{ 'dz-drag-hover': isOverDropZone && !props.disabled, [props.class]: true }"
        :style="{ pointerEvents: props.disabled ? 'none' : undefined }"
        @click.prevent="handleClick"
        @drop.prevent="handleDrop"
        @dragover="handleDragOver"
        @dragleave="isOverDropZone = false"
        @dragend="isOverDropZone = false"
    >
        <input :id="props.id" type="file" :accept="props.accept" :name="props.inputName" />
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
