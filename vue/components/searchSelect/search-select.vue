<script setup lang="ts">
/**
 * To search for results with a query and select them
 */

import { useTemplateRef, defineModel } from "vue";
import { useSelected, type Item } from "@schoolboxVue/composables/store/useSelected";
import { useSearch } from "./composables/useSearch";
import type { SearchSelectItem } from "./types/searchSelectTypes";
import SsSelectedList from "./ss-selected-list.vue";
import SsQueryInput from "./ss-query-input.vue";
import SsResultsList from "./ss-results-list.vue";

interface Props {
    label: string; // the label text
    labelId: string; // the label id
    isRequired?: boolean; // whether the input requires a value
    endpoint: string; // the search api endpoint
    defaultResults?: SearchSelectItem[]; // show selection options when the search input is empty
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

const selectedItems = defineModel<Item[]>("selected-items", { default: [] }); // to store selected items
const { add, remove } = useSelected(selectedItems); // to add and remove selected items

const searchSelectEl = useTemplateRef("searchSelectEl");

const { query, isActive, isSearching, hasNoResults, results } = useSearch(props.endpoint); // to search for results

/**
 * To show the current search results, or the default results
 * if the search is not active
 * @returns - the current results
 */
const currentResults = window.Vue.computed(() => {
    if (isActive.value) {
        return results.value;
    }
    if (props.defaultResults && props.defaultResults.length > 0) {
        return props.defaultResults;
    }
    return results.value;
});

/**
 * To add results
 * @param $items - the items selected
 */
function onAdd($items: SearchSelectItem[]): void {
    // if single select, remove the previous selection
    if (props.isSingleSelect && selectedItems.value.length > 0) {
        remove($items);
    }
    add($items);
}

/**
 * To remove results
 * @param $items - the items deselected
 */
function onRemove($items: SearchSelectItem[]): void {
    remove($items);
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
    <div ref="searchSelectEl">
        <label :for="props.labelId" :class="`${props.isRequired ? 'required' : ''}`">{{ props.label }}</label>
        <ss-selected-list
            :selected-items="selectedItems"
            :form-value-name="props.formValueName"
            :is-single-select="props.isSingleSelect"
            @remove="onRemove"
        />
        <ss-query-input
            :label-id="props.labelId"
            :parent-el="searchSelectEl"
            :placeholder="props.placeholder"
            @input="onInput"
        />
        <ss-results-list
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
