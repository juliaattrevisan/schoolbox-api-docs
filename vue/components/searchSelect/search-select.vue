<script setup lang="ts">
/**
 * To search for searchResults and select them
 */

import { useSearch } from "@schoolboxVue/composables/search/useSearch";
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

const searchSelectEl = window.Vue.ref<HTMLDivElement | null>(null);

const { query, isActive, isSearching, hasNoResults, results } = useSearch(props.endpoint);

/**
 * To show the current results, or the default results if the search is not active
 * @returns { Result[] }
 */
const currentResults = window.Vue.computed(() => {
    if (props.selected.length === 1 && props.isSingleSelect) {
        return [];
    }
    if (props.defaultResults && props.defaultResults.length > 0 && !isActive.value) {
        return props.defaultResults;
    }
    return results.value;
});

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
            :selected="props.selected"
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
            :selected="selected"
            @select="onSelected"
        />
    </div>
</template>

<style scoped></style>
