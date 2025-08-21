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
     * Should the input field be required?
     * This is only useful if inputName is specified and this is being used as a HTML form input
     */
    required?: boolean;
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
    required: false,
    disabled: false,
    fileHandler: () => true,
});

const currentFile = defineModel<FileDropFile | null>("currentFile", { default: null });

const { reset, open, onChange, onCancel } = useFileDialog({
    accept: props.accept,
    multiple: false,
});

const inputEl = window.Vue.useTemplateRef<HTMLInputElement>("inputRef");

/**
 * Keep the hidden input in sync with currentFile
 */
window.Vue.watch(
    currentFile,
    (file) => {
        if (inputEl.value === null) {
            return;
        }

        // no file? clear it
        if (file === null || file.error !== false) {
            inputEl.value.value = "";
            inputEl.value.files = null;
            return;
        }

        // otherwise, set the file
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file.file);
        inputEl.value.files = dataTransfer.files;
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
    <div>
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
            <div class="dzone icon-attach">
                <span>{{ props.placeholder }}</span>
            </div>
        </label>

        <!-- Always render this hidden input so that forms can access it for validation -->
        <input
            :id="props.id"
            ref="inputRef"
            type="file"
            :accept="props.accept"
            :name="props.inputName"
            :required="props.required || undefined"
        />
    </div>
</template>

<style scoped lang="scss">
label.attachzone {
    font-weight: normal;
}

/** Hide the actual file input, but still allow the browser to validate it */
input {
    opacity: 0;
    position: absolute;
    pointer-events: none;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
}
</style>
