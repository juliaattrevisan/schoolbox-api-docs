<script lang="ts" setup>
/**
 * A hidden file input that is kept in sync with a v-model currentFiles
 */

import type { FileDropFile } from "./fileDropTypes";

interface Props {
    id: string;
    accept?: string;
    inputName?: string;
    required?: boolean;
    multiple?: boolean;
    currentFiles: (FileDropFile | null)[];
}

const props = defineProps<Props>();

const inputEl = window.Vue.useTemplateRef<HTMLInputElement>("inputRef");

/**
 * Keep the hidden input in sync with currentFile
 */
window.Vue.watch(
    () => props.currentFiles,
    (files) => {
        if (inputEl.value === null) {
            return;
        }

        // filter out files with errors (two filters so TS can derive the correct type)
        const validFiles = files.filter((file) => file !== null).filter((file) => !file.error);

        // no files? clear it
        if (validFiles.length === 0) {
            inputEl.value.value = "";
            inputEl.value.files = null;
            return;
        }

        // otherwise, set the files
        const dataTransfer = new DataTransfer();
        validFiles.forEach((file) => {
            dataTransfer.items.add(file.file);
        });
        inputEl.value.files = dataTransfer.files;
    },
    { immediate: true },
);
</script>

<template>
    <input
        :id="props.id"
        ref="inputRef"
        type="file"
        :accept="props.accept"
        :name="props.inputName"
        :required="props.required || undefined"
        :multiple="props.multiple || undefined"
        autocomplete="off"
    />
</template>

<style scoped lang="scss"></style>
