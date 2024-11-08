<script setup lang="ts">
/**
 * To input the search query
 */

import { useTemplateRef } from "vue";
import { useFocus, onKeyStroke } from "@vueuse/core";

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
const inputEl = useTemplateRef<HTMLInputElement | null>("inputEl");
const { focused } = useFocus(inputEl);
onKeyStroke(["Escape"], () => {
    if (focused.value) {
        emit("escape");
    }
});
</script>

<template>
    <input
        :id="props.labelId"
        ref="inputEl"
        v-model="query"
        type="text"
        class="search-input f-no-margins"
        :placeholder="props.placeholder"
        data-test="ss-query-input"
    />
</template>
