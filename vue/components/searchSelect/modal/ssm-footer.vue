<script setup lang="ts">
/**
 * Display the modal buttons
 */

import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";

interface Props {
    savedSelected: SearchSelectItem[]; // whether there are currently any selected items
}
const props = defineProps<Props>();

const emit = defineEmits<{
    cancel: [];
    select: [];
}>();

/**
 * To prompt the modal close
 */
function onCancel(): void {
    emit("cancel");
}

/**
 * To prompt the modal select
 */
function onSelect(): void {
    emit("select");
}

/**
 * Changes the text of the button depending on whether there are any selected items
 * @returns - the text to display on the button
 */
const saveText = window.Vue.computed(() => (props.savedSelected.length === 0 ? "Select" : "Modify"));
</script>

<template>
    <ul class="flex-list buttons">
        <li><button type="button" class="button" :disabled="false" @click.prevent="onCancel">Cancel</button></li>
        <li>
            <button type="button" class="button submit" @click.prevent="onSelect">
                {{ saveText }}
            </button>
        </li>
    </ul>
</template>

<style scoped></style>
