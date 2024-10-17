<script setup lang="ts">
/**
 * To search for results with a query and select them
 */

import { useTemplateRef } from "vue";
import { useSearch } from "./composables/useSearch";
import type { Result } from "./types/Result";
import SearchSelectSelected from "./search-select-selected.vue";
import SearchSelectInput from "./search-select-input.vue";
import SearchSelectResults from "./search-select-results.vue";

interface Props {
    label: string; // the label text
    labelId: string; // the label id
    isRequired?: boolean; // whether the input requires a value
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
    add: [results: Result[]];
    remove: [results: Result[]];
}>();

const searchSelectEl = useTemplateRef("searchSelectEl");

const { query, isActive, isSearching, hasNoResults, results } = useSearch(props.endpoint);

/**
 * To show the current search results, or the default results
 * if the search is not active
 * @returns - the current results
 */
const currentResults = window.Vue.computed(() => {
    if (props.defaultResults && props.defaultResults.length > 0 && !isActive.value) {
        return props.defaultResults;
    }
    return results.value;
});

/**
 * To add results
 * @param $results - the results selected
 */
function onAdd($results: Result[]): void {
    // if single select, remove the previous selection
    if (props.isSingleSelect) {
        emit("remove", props.selectedItems);
    }
    emit("add", $results);
}

/**
 * To remove results
 * @param $results - the results deselected
 */
function onRemove($results: Result[]): void {
    emit("remove", $results);
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
        <label :for="props.labelId" :class="`${props.isRequired ? 'required' : ''}`">{{ props.label }}</label>
        <search-select-selected
            :selected-items="props.selectedItems"
            :form-value-name="props.formValueName"
            :is-single-select="props.isSingleSelect"
            @remove="onRemove"
        />
        <search-select-input
            :label-id="props.labelId"
            :parent-el="searchSelectEl"
            :placeholder="props.placeholder"
            @input="onInput"
        />
        <search-select-results
            :query="query"
            :is-active="isActive"
            :is-searching="isSearching"
            :is-single-select="props.isSingleSelect"
            :results="currentResults"
            :has-no-results="hasNoResults"
            :selected-items="selectedItems"
            @add="onAdd"
            @remove="onRemove"
        />
    </div>
</template>

<style scoped></style>
