<script setup lang="ts">
/**
 * To search for results with a query and select them
 */

// import { useTemplateRef } from "vue";
import { useSelected } from "@schoolboxVue/composables/store/useSelected";
import { useSearch } from "./composables/useSearch";
import type { SearchSelectItem } from "./types/searchSelectTypes";
import SsSelected from "./ss-selected.vue";
import SsQueryInput from "./ss-query-input.vue";
import SsResults from "./ss-results.vue";

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

// const searchSelectEl = useTemplateRef("searchSelectEl"); // failing typecheck currently :(
const searchSelectEl = window.Vue.ref<HTMLDivElement | null>(null);

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
 * To add items
 * @param $items - the items selected
 */
function onAdd($items: SearchSelectItem[]): void {
    // if single select, switch with the previous selection
    if (props.isSingleSelect && selected.value.length > 0) {
        update($items);
        return;
    }
    add($items);
}

/**
 * To remove items
 * @param $items - the items deselected
 */
function onRemove($items: SearchSelectItem[]): void {
    remove($items);
}
</script>

<template>
    <div ref="searchSelectEl">
        <label :for="props.labelId" :class="`${props.isRequired ? 'required' : ''}`">{{ props.label }}</label>
        <ss-selected
            :selected="selected"
            :form-value-name="props.formValueName"
            :is-single-select="props.isSingleSelect"
            @remove="onRemove"
        />
        <ss-query-input
            v-model:query="query"
            :label-id="props.labelId"
            :parent-el="searchSelectEl"
            :placeholder="props.placeholder"
        />
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
