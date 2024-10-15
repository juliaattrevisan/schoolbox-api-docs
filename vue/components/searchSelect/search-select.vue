<script setup lang="ts">
/**
 * To search for results with a query and select them
 */

import { useSearch } from "./composables/useSearch";
import type { Result } from "./types/Result";
import SearchSelectSelected from "./search-select-selected.vue";
import SearchSelectInput from "./search-select-input.vue";
import SearchSelectResults from "./search-select-results.vue";

interface Props {
    endpoint: string; // the search api endpoint
    selectedItems: Result[]; // the selected searchResults
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
    selectResult: [result: Result]; // to select a search result
    removeItem: [result: Result]; // to remove a selected item
    selectAllResults: [results: Result[]]; // to select all search results
    removeAllResults: [results: Result[]]; // to remove all search results
}>();

const searchSelectEl = window.Vue.ref<HTMLDivElement | null>(null);

const { query, isActive, isSearching, hasNoResults, results } = useSearch(props.endpoint);

/**
 * To show the current search results, or the default results if the search is not active
 * @returns { Result[] }
 */
const currentResults = window.Vue.computed(() => {
    if (props.selectedItems.length === 1 && props.isSingleSelect) {
        return [];
    }
    if (props.defaultResults && props.defaultResults.length > 0 && !isActive.value) {
        return props.defaultResults;
    }
    return results.value;
});

/**
 * To remove a result
 * @param result - the result removed
 */
function onRemoved(result: Result): void {
    emit("removeItem", result);
}

/**
 * To toggle the selected state of the result
 * @param result - the result selected
 */
function onSelect(result: Result): void {
    emit("selectResult", result);
}

/**
 * To toggle the selected state of the result
 * @param result - the result selected
 */
function onDeselect(result: Result): void {
    emit("removeItem", result);
}

/**
 * To select all search results
 */
function onSelectAll(): void {
    emit("selectAllResults", results.value);
}

/**
 * To remove all search results
 */
function onRemoveAll(): void {
    emit("removeAllResults", results.value);
}

/**
 * To update the search query
 * @param $query - the search query
 */
function onInput($query: string): void {
    query.value = $query;
}
</script>

<template>
    <div ref="searchSelectEl" class="c-search-select">
        <search-select-selected
            :selected-items="props.selectedItems"
            :form-value-name="props.formValueName"
            :is-single-select="props.isSingleSelect"
            @remove="onRemoved"
        />
        <search-select-input :parent-el="searchSelectEl" :placeholder="props.placeholder" @input="onInput" />
        <search-select-results
            :query="query"
            :is-active="isActive"
            :is-searching="isSearching"
            :is-single-select="props.isSingleSelect"
            :results="currentResults"
            :has-no-results="hasNoResults"
            :selected-items="selectedItems"
            @select="onSelect"
            @deselect="onDeselect"
            @select-all="onSelectAll"
            @remove-all="onRemoveAll"
        />
    </div>
</template>

<style scoped></style>
