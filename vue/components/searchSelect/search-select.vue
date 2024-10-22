<script setup lang="ts">
/**
 * To search for results with a query and select them
 */

import { useTemplateRef } from "vue";
import { useSelected } from "@schoolboxVue/composables/store/useSelected";
import { useSearch } from "./composables/useSearch";
import type { SearchSelectItem } from "./types/searchSelectTypes";
import SsSelected from "./ss-selected.vue";
import SsQueryInput from "./ss-query-input.vue";
import SsResults from "./ss-results.vue";

interface Props {
    label: string; // the label text
    labelId: string; // the label id
    endpoint: string; // the search api endpoint
    isRequired?: boolean; // whether the input requires a value
    defaultResults?: SearchSelectItem[]; // show selection options when the search input is empty
    isSingleSelect?: boolean; // whether only one result can be selected
    formValueName?: string; // the name of the form value
    placeholder?: string; // the query input placeholder text
    canSelectItem?: boolean; // whether the user can select an item (to trigger the select emit)
    hasError: boolean; // Whether the input has an error
    errorMessage: string; // The error message to display
}
const props = withDefaults(defineProps<Props>(), {
    defaultResults: undefined,
    formValueName: "",
    placeholder: "Search...",
});

const emit = defineEmits<{
    select: [item: SearchSelectItem];
}>();

const searchSelectEl = useTemplateRef<HTMLDivElement | null>("searchSelectEl");

const selected = defineModel<SearchSelectItem[]>("selected", { default: [] }); // to store selected items
const { add, remove, update } = useSelected(selected); // to add and remove selected items

const results = defineModel<SearchSelectItem[]>("results", { default: [] }); // to store search results
const { query, isActive, isSearching, hasNoResults } = useSearch(props.endpoint, results); // to search for results

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
 * A selected item name has been selected
 * @param item - the items selected
 */
function onSelect(item: SearchSelectItem): void {
    emit("select", item);
}

/**
 * To add items
 * @param items - the items selected
 */
function onAdd(items: SearchSelectItem[]): void {
    // if single select, switch with the previous selection
    if (props.isSingleSelect && selected.value.length > 0) {
        update(items);
        return;
    }
    add(items);
}

/**
 * To remove items
 * @param items - the items deselected
 */
function onRemove(items: SearchSelectItem[]): void {
    remove(items);
}
</script>

<template>
    <div ref="searchSelectEl">
        <label :for="props.labelId" :class="`${props.isRequired ? 'required' : ''}`">{{ props.label }}</label>
        <ss-selected
            :selected="selected"
            :form-value-name="props.formValueName"
            :is-single-select="props.isSingleSelect"
            :can-select-item="props.canSelectItem"
            @select="onSelect"
            @remove="onRemove"
        />
        <ss-query-input
            v-model:query="query"
            :label-id="props.labelId"
            :parent-el="searchSelectEl"
            :placeholder="props.placeholder"
        />
        <label v-if="props.hasError" class="error" :for="props.labelId">{{ errorMessage }}</label>
        <ss-results
            :query="query"
            :is-active="isActive"
            :is-searching="isSearching"
            :is-single-select="props.isSingleSelect"
            :results="currentResults"
            :has-no-results="hasNoResults"
            :selected="selected"
            @add="onAdd"
            @remove="onRemove"
        />
    </div>
</template>
