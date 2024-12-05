<script setup lang="ts">
/**
 * To input the search query
 */

import { onKeyStroke } from "@vueuse/core";
import { vOnKeyStroke } from "@vueuse/components";

interface Props {
    labelId: string; // the label id
    placeholder: string; // the placeholder text
}
const props = defineProps<Props>();

const emit = defineEmits<{
    escape: [];
}>();

const query = defineModel<string>("query", { default: "" }); // to store the search query

/**
 * Prevent the form from submitting when pressing Enter in the search input,
 * and the text input from shifting to the start/end on ArrowUp/ArrowDown
 */
onKeyStroke(["Enter", "ArrowUp", "ArrowDown"], (event) => {
    event.preventDefault();
});

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
        v-on-key-stroke:Escape="onEscape"
        type="text"
        class="search-input f-no-margins"
        :placeholder="props.placeholder"
        data-test="ss-query-input"
    />
</template>
