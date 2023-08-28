<script setup lang="ts">
/**
 * To search for results and select them
 */

import { useFetch, useDebounceFn } from "@vueuse/core";
import type { Result } from "./types/Result";
import SearchSelectSelected from "./search-select-selected.vue";
import SearchSelectInput from "./search-select-input.vue";
import SearchSelectResults from "./search-select-results.vue";

interface Props {
    endpoint: string; // the search api endpoint
    selected: Result[]; // the selected results
    isSingleSelect?: boolean; // whether only one result can be selected
}
const props = withDefaults(defineProps<Props>(), {
    isSingleSelect: false,
});

const emit = defineEmits<{
    selected: [result: Result];
}>();

const query = window.Vue.ref<string>("");
const results = window.Vue.ref<Result[]>([]);
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
    error: hasError, // to notify of a search error
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

// const search = useDebounceFn(async() => {
//     await execute();
// }, 300);

// const search = useDebounceFn(async () => {
//     await execute();
// }, 300);

// const debouncedRequest = useDebounceFn(() => execute, 300);

// async function search() {
//     await debouncedRequest();
// }

/**
 * start searching once the keyword length is greater than the minimum
 * @param {string} value - The input value
 */
async function onInput(value: string) {
    query.value = value;
    if (query.value.length < minKeywordLength) {
        return;
    }
    await execute();
    // await debouncedRequest();
    try {
        results.value = data.value as Results;
    } catch (error) {
        hasError.value = true;
    }
}

/**
 * To toggle the selected state of the result
 * @param {Result} result - the result selected
 */
function onSelected(result: Result) {
    emit("selected", result);
}
</script>

<template>
    <div class="c-search-select">
        <search-select-selected />
        <search-select-input @input="onInput" />
        <search-select-results
            :selected="selected"
            :results="results"
            :is-searching="isFetching"
            :is-single-select="props.isSingleSelect"
            @selected="onSelected"
        />
    </div>
</template>

<style scoped></style>
