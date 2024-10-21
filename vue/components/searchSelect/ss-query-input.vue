<script setup lang="ts">
/**
 * To input the search query
 */

import { onClickOutside, useFocus, onKeyStroke } from "@vueuse/core";

interface Props {
    labelId: string; // the label id
    parentEl: HTMLDivElement | null; // the parent element to close the search results
    placeholder: string; // the placeholder text
}
const props = defineProps<Props>();

const query = defineModel<string>("query", { default: "" });

/**
 * To close the search results when clicking outside
 */
window.Vue.onMounted(async () => {
    await window.Vue.nextTick();
    onClickOutside(props.parentEl, () => {
        query.value = "";
    });
});

/**
 * To close the search results when the input is not focused
 */
const { focused } = useFocus(props.parentEl);
window.Vue.watch(focused, ($focused) => {
    if (!$focused) {
        query.value = "";
    }
});

/**
 * Prevent the form from submitting when pressing Enter in the search input,
 * and the text input from shifting to the start/end on ArrowUp/ArrowDown
 */
onKeyStroke(["Enter", "ArrowUp", "ArrowDown"], (event) => {
    event.preventDefault();
});
</script>

<template>
    <input
        :id="props.labelId"
        v-model="query"
        type="text"
        class="search-input f-no-margins"
        :placeholder="props.placeholder"
        data-test="ss-query-input"
    />
</template>
