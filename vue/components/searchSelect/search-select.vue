<script setup lang="ts">
/**
 * To search for searchResults and select them
 */

import { useFetch, useDebounceFn } from "@vueuse/core";
import type { Result } from "./types/Result";
import SearchSelectSelected from "./search-select-selected.vue";
import SearchSelectInput from "./search-select-input.vue";
import SearchSelectResults from "./search-select-results.vue";

interface Props {
    endpoint: string; // the search api endpoint
    selected: Result[]; // the selected searchResults
    defaultResults?: Result[]; // show selection options when the search input is empty
    isSingleSelect?: boolean; // whether only one result can be selected
    formValueName?: string; // the name of the form value
}
const props = withDefaults(defineProps<Props>(), {
    defaultResults: undefined,
    isSingleSelect: false,
    formValueName: "",
});

const emit = defineEmits<{
    select: [result: Result];
    remove: [result: Result];
}>();

const query = window.Vue.ref<string>("");
const searchResults = window.Vue.ref<Result[]>([]);
const canSearch = window.Vue.ref<boolean>(false);
const minKeywordLength = 2;

const url = window.Vue.computed(() => {
    return `${props.endpoint}${query.value}`;
});

/**
 * Return the default results if the query is empty
 */
const results = window.Vue.computed(() => {
    if (props.defaultResults && !canSearch.value) {
        return props.defaultResults;
    }
    return searchResults.value;
});

/**
 * Submit the current query, using _debounce to accommodate for key presses
 * so not sending on every key press
 */
const {
    execute,
    data, // the search searchResults
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
        canSearch.value = false;
        searchResults.value = [];
        return;
    }
    canSearch.value = true;
    await execute();
    // await debouncedRequest(); // @TODO - resolve debounce issue
    try {
        searchResults.value = (data as Ref<Result[]>).value;
    } catch (error) {
        hasError.value = true;
    }
}

/**
 * To toggle the selected state of the result
 * @param {Result} result - the result selected
 */
function onSelected(result: Result) {
    emit("select", result);
}

/**
 * To remove a result
 * @param {Result} result - the result removed
 */
function onRemoved(result: Result) {
    emit("remove", result);
}
</script>

<template>
    <div class="c-search-select">
        <search-select-selected
            :selected="props.selected"
            :form-value-name="props.formValueName"
            :is-single-select="props.isSingleSelect"
            @remove="onRemoved"
        />
        <search-select-input @input="onInput" />
        <search-select-results
            :query="query"
            :is-searching="isFetching"
            :is-single-select="props.isSingleSelect"
            :results="results"
            :selected="selected"
            @select="onSelected"
        />
    </div>
</template>

<style scoped></style>
