<script lang="ts" setup>
/**
 * A hidden file input that is kept in sync with a v-model currentFile
 */

import type { FileDropFile } from "./fileDropTypes";

interface Props {
    id: string;
    accept?: string;
    class?: string;
    inputName?: string;
    required?: boolean;
}

const props = defineProps<Props>();

const currentFile = defineModel<FileDropFile>("currentFile", { default: null });

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
</script>

<template>
    <input
        :id="props.id"
        ref="inputRef"
        type="file"
        :accept="props.accept"
        :name="props.inputName"
        :required="props.required || undefined"
        autocomplete="off"
    />
</template>

<style scoped lang="scss"></style>
