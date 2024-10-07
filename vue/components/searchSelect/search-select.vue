<script setup lang="ts">
/**
 * To search for searchResults and select them
 */

import type { Ref } from "vue";
import { useFetch, useDebounceFn } from "@vueuse/core";
import type { Result } from "./types/Result";
import SearchSelectSelected from "./search-select-selected.vue";
import SearchSelectInput from "./search-select-input.vue";
import SearchSelectResults from "./search-select-results.vue";
import { onClickOutside } from "@vueuse/core";

interface Props {
    endpoint: string; // the search api endpoint
    selected: Result[]; // the selected searchResults
    defaultResults?: Result[]; // show selection options when the search input is empty
    isSingleSelect?: boolean; // whether only one result can be selected
    formValueName?: string; // the name of the form value
    placeholder?: string; // the placeholder text
}
const props = withDefaults(defineProps<Props>(), {
    defaultResults: undefined,
    isSingleSelect: false,
    formValueName: "",
    placeholder: "Search...",
});

const emit = defineEmits<{
    select: [result: Result];
    remove: [result: Result];
}>();

const query = window.Vue.ref<string>("");
const searchResults = window.Vue.ref<Result[]>([]);
const canSearch = window.Vue.ref<boolean>(false);
const minKeywordLength = 2;
const searchSelectEl = window.Vue.ref<HTMLDivElement | null>(null);

const url = window.Vue.computed(() => {
    return `${props.endpoint}${query.value}`;
});

/**
 * Return the results depending on the current state
 */
const results = window.Vue.computed(() => {
    if (props.selected.length === 1 && props.isSingleSelect) {
        return [];
    }
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
    abort,
} = useFetch(url, {
    immediate: false,
    onFetchError(ctx: { response: Response | null; error: unknown; data: unknown }) {
        return ctx;
    },
})
    .get()
    .json();

/**
 * Debounce the search so we don't send a request on every key press
 */
// eslint-disable-next-line @typescript-eslint/no-misused-promises
const search = useDebounceFn(async () => {
    await execute();
    try {
        searchResults.value = (data as Ref<Result[]>).value;
    } catch (error) {
        hasError.value = true;
    }
}, 300);

/**
 * start searching once the keyword length is greater than the minimum
 * @param value - The input value
 */
async function onInput(value: string): Promise<void> {
    query.value = value;
    if (query.value.length < minKeywordLength) {
        canSearch.value = false;
        searchResults.value = [];
        return;
    }
    canSearch.value = true;
    await search();
}

/**
 * To toggle the selected state of the result
 * @param result - the result selected
 */
function onSelected(result: Result): void {
    emit("select", result);
}

/**
 * To remove a result
 * @param result - the result removed
 */
function onRemoved(result: Result): void {
    emit("remove", result);
}

/**
 * To close the search results when clicking outside
 */
onClickOutside(searchSelectEl, () => {
    abort();
    searchResults.value = [];
});
</script>

<template>
    <div ref="searchSelectEl" class="c-search-select">
        <search-select-selected
            :selected="props.selected"
            :form-value-name="props.formValueName"
            :is-single-select="props.isSingleSelect"
            @remove="onRemoved"
        />
        <search-select-input :placeholder="props.placeholder" @input="onInput" />
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
