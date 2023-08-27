<script setup lang="ts">
/**
 * To search for results and select them
 */

import { useFetch, useDebounceFn } from "@vueuse/core";
import SearchSelectSelected from "./search-select-selected.vue";
import SearchSelectInput from "./search-select-input.vue";
import SearchSelectResults from "./search-select-results.vue";

interface Props {
    endpoint: string; // the search api endpoint
    selected: Array<any>; // the selected results
}
const props = defineProps<Props>();
const query = window.Vue.ref<string>("");
const minKeywordLength = 2;

const url = window.Vue.computed(() => {
    return `${props.endpoint}${query.value}`;
});

/**
 * Submit the current query, using _debounce to accommodate for key presses
 * so not sending on every key press
 */
const {
    execute,
    data, // the search results
    isFetching, // to notify of searching
    error, // to notify of a search error
} = useFetch(url, {
    immediate: false,
    onFetchError(ctx) {
        // we catch the Search error to display the error message
        console.warn(ctx);
        // if ((ctx.data as SearchError).status) {
        //     searchError.value = ctx.data as SearchError;
        // }
        return ctx;
    },
})
    .get()
    .json();

const search = useDebounceFn(() => {
    void execute();
}, 300);

/**
 * start searching once the keyword length is greater than the minimum
 * @param {string} value - The input value
 */
async function onInput(value: string) {
    query.value = value;
    if (query.value.length < minKeywordLength) {
        return;
    }
    await search();
}
</script>

<template>
    <div class="c-search-select">
        <search-select-selected />
        <search-select-input @input="onInput" />
        <search-select-results :results="data" />
    </div>
</template>

<style scoped></style>
