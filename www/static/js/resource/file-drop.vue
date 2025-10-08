<script lang="ts" setup>
/**
 * A basic file drop that is styled like a normal resource attach dropzone
 * This currently only supports a single file at a time, but could be extended to support multiple files
 */

import { useFileDialog } from "@vueuse/core";
import FileDropPreview from "./file-drop-preview.vue";
import type { FileDropFile } from "./fileDropTypes";
import { uploadFileToStorage } from "./resourceUtils";

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
     * Should the file be automatically uploaded to storage when selected?
     */
    autoUploadToStorage?: boolean;
    /**
     * Should this allow multiple files to be selected?
     * If a number is specified, that is the maximum number of files that can be selected
     */
    multiple?: number;
    /**
     * The actual length of the files that are already uploaded/selected
     * This is used if the parent removes files, but the multiple limit should still apply based on a different factor
     */
    actualFilesLength?: number;
    /**
     * Handler for the File object when a file is selected
     * @param file - The file object
     * @returns Whether the file should be considered as valid or not, return a string to specify an error message
     */
    fileHandler?: (file: File) => Promise<boolean | string> | boolean | string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: undefined,
    accept: "*/*",
    class: "l-mar-s--bottom",
    inputName: undefined,
    required: false,
    disabled: false,
    autoUploadToStorage: false,
    multiple: undefined,
    actualFilesLength: undefined,
    fileHandler: () => true,
});

const emit = defineEmits<{ (e: "file-uploaded", file: FileDropFile): void }>();

const currentFile = defineModel<FileDropFile | null>("currentFile", { default: null });
const currentFiles = defineModel<FileDropFile[]>("currentFiles", { default: [] });

const { reset, open, onChange, onCancel } = useFileDialog({
    accept: props.accept,
    multiple: !!props.multiple,
});

const filesList = window.Vue.computed<FileDropFile[]>(() => {
    if (props.multiple) {
        return currentFiles.value;
    }
    if (currentFile.value === null) {
        return [];
    }
    return [currentFile.value];
});

/**
 * When the file import is cancelled
 */
function handleCancel(): void {
    while (currentFiles.value.pop()) {
        // remove all files without changing the reference
    }

    currentFile.value = null;
}

/**
 * When the file import is clicked
 */
function handleClick(): void {
    reset();
    open();
}

const uploadingFiles = window.Vue.ref<FileDropFile[]>([]);

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

    currentFiles.value.push(file);
    currentFile.value = file;

    if (props.autoUploadToStorage && !file.storageHash) {
        // If the file is valid, we can upload it
        uploadingFiles.value.push(file);
        const resource = await uploadFileToStorage(file.file);
        uploadingFiles.value = uploadingFiles.value.filter((f) => f !== file);

        if (resource.success === false) {
            let errorMessage = resource.message || "Failed to upload file";

            // Try to pull the message from the response if available
            if (
                typeof resource.data === "object" &&
                resource.data !== null &&
                "message" in resource.data &&
                typeof resource.data.message === "string"
            ) {
                errorMessage = resource.data.message || errorMessage;
            }

            file.error = errorMessage;
            return;
        }

        file.storageHash = resource.data.hash;
        file.storageFile = resource.data.meta.file;

        emit("file-uploaded", file);
    }
}

/**
 * When the user removes a file
 * @param file - The file to remove
 */
function handleRemove(file: FileDropFile): void {
    if (props.disabled) {
        return; // don't allow removing if disabled
    }

    const index = currentFiles.value.indexOf(file);
    if (index !== -1) {
        currentFiles.value.splice(index, 1);
    }

    currentFile.value = null;
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

    let filesAdded = 0;
    [...files].forEach((fileListFile) => {
        const file = { file: fileListFile, error: false } as const;

        // Is the file the right type?
        if (props.accept !== "*/*" && !window.Dropzone.isValidFile(file.file, props.accept)) {
            currentFile.value = {
                ...file,
                error: "You can't upload files of this type.",
            };

            currentFiles.value.push({ ...currentFile.value });
            return;
        }

        filesAdded += 1;
        if (props.multiple && filesAdded > props.multiple - (props.actualFilesLength || currentFiles.value.length)) {
            currentFiles.value.push({
                ...file,
                error: `You can only upload up to ${props.multiple} files.`,
            });
            return;
        }

        void handleLoad(file);
    });
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
        <template v-if="filesList.length > 0">
            <div
                v-for="(file, index) in filesList"
                :key="`${index}-${file.storageHash}`"
                class="ab-upload ab-upload-panel"
                :class="props.class"
            >
                <div class="dzone dz-clickable dz-started" style="border: 0">
                    <file-drop-preview
                        :file="file"
                        :disabled="props.disabled"
                        :is-uploading="uploadingFiles.includes(file)"
                        @remove="() => handleRemove(file)"
                    />
                </div>
            </div>
        </template>

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
                <span>
                    {{ props.placeholder || `Click or drag here to attach ${props.multiple ? "files" : "a file"}` }}
                </span>
            </div>
        </label>

        <!-- Always render this hidden input so that forms can access it for validation -->
        <input
            :id="props.id"
            ref="inputRef"
            type="file"
            :accept="props.accept"
            :multiple="!!props.multiple || undefined"
            :name="props.inputName"
            :required="props.required || undefined"
            autocomplete="off"
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
