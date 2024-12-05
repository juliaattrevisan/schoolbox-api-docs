<script setup lang="ts">
/**
 * To input the search query
 */

import { vOnKeyStroke } from "@vueuse/components";

interface Props {
    labelId: string; // the label id
    placeholder: string; // the placeholder text
    showResults?: boolean; // whether to show the search results
}
const props = defineProps<Props>();

const emit = defineEmits<{
    escape: [];
}>();

const query = defineModel<string>("query", { default: "" }); // to store the search query

/**
 * When the search results are open, prevent the form from submitting when pressing Enter in the search input,
 * and the text input from shifting to the start/end on ArrowUp/ArrowDown
 * @param event - the keyboard event
 */
function onKeySelect(event: KeyboardEvent): void {
    if (props.showResults) {
        event.preventDefault();
    }
}

/**
 * To close the search results when pressing Escape
 */
function onEscape(): void {
    emit("escape");
}
</script>

<template>
    <input
        :id="props.labelId"
        ref="inputEl"
        v-model="query"
        v-on-key-stroke:Enter,ArrowUp,ArrowDown="onKeySelect"
        v-on-key-stroke:Escape="onEscape"
        type="text"
        class="search-input f-no-margins"
        :placeholder="props.placeholder"
        data-test="ss-query-input"
    />
</template>
