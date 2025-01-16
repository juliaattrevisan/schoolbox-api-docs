<script setup lang="ts">
/**
 * To search for results with a query and select them
 */

import { toRef, useTemplateRef } from "vue";
import { vOnClickOutside } from "@vueuse/components";
import { useSelected } from "@schoolboxVue/composables/selected/useSelected";
import { useQuerySearch } from "@schoolboxVue/composables/search/useQuerySearch";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import { useOnSelectedChange } from "./composables/useOnSelectedChange";
import SsSelected from "./ss-selected.vue";
import SsQueryInput from "./ss-query-input.vue";
import SsResults from "./ss-results.vue";

interface Props {
    label: string; // the label text
    labelId: string; // the label id
    endpoint: string; // the search api endpoint, expects an endpoint without a `query` parameter, which will be added via useQuerySearch
    queryKey?: string; // the key to use for search
    manageSearchResponse?: boolean; // whether to manage the response internally, or dispatch an emit to manage it externally
    isRequired?: boolean; // whether the input requires a value
    defaultResults?: SearchSelectItem[]; // show selection options when the search input is empty
    isSingleSelect?: boolean; // whether only one result can be selected
    formValueName?: string; // the name of the form value
    placeholder?: string; // the query input placeholder text
    canSelectItem?: boolean; // whether the user can select an item (to trigger the select emit)
    hasError?: boolean; // Whether the input has an error
    errorMessage?: string; // The error message to display
    isReadOnly?: boolean; // Whether the input is read only
    allowPartialMatches?: boolean; // whether to allow partial matches, so can match part of a word
    hasButton?: boolean; // whether to show a button next to the input
    buttonIcon?: string; // the icon to display on the button
    buttonTitle?: string; // the title of the button
}
const props = withDefaults(defineProps<Props>(), {
    defaultResults: undefined,
    formValueName: "",
    placeholder: "Search...",
    errorMessage: "This field is required",
    buttonIcon: "",
    buttonTitle: "Browse",
    queryKey: "query",
});

const emit = defineEmits<{
    searchResponse: [json: unknown];
    select: [item: SearchSelectItem];
    buttonSelect: [];
}>();

// #region Selected  Items

const selected = defineModel<SearchSelectItem[]>("selected", { default: [] }); // to store selected items
const { add, remove, update } = useSelected(selected); // to add and remove selected items
const { onAdd, onRemove } = useOnSelectedChange(
    toRef(() => props.isSingleSelect),
    selected,
    add,
    remove,
    update,
); // to handle selected item changes picked up within the <template/>

// #endregion

// #region Search Results

const results = defineModel<SearchSelectItem[]>("results", { default: [] }); // to store search results
/**
 * To handle the search response, we handle some basic responses internally,
 * but if the response is more complex, we emit the response to be handled externally
 * @param json - the returned json
 */
function onSearchSuccess(json: unknown): void {
    // emit to handle the response externally, as the response object is typically something not handled by the the basic structure below
    if (props.manageSearchResponse) {
        emit("searchResponse", json);
        return;
    }
    // if json is an array, set results to json
    if (json instanceof Array) {
        results.value = json as SearchSelectItem[];
        return;
    }
    // if json.data exists, set results to json.data
    if (typeof json === "object" && json !== null && "data" in json) {
        results.value = (json as { data: SearchSelectItem[] }).data;
    }
}
const { query, isActive, isSearching, hasNoResults, hasError } = useQuerySearch(
    props.endpoint,
    props.queryKey,
    results,
    onSearchSuccess,
); // to search for results

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

// #endregion

// #region close search results on lost focus / click outside

const searchSelectEl = useTemplateRef<HTMLDivElement | null>("searchSelectEl"); // to clear the search results when clicking outside / not focused

/**
 * To close the search results
 */
function closeResults(): void {
    query.value = "";
}

/**
 * To close the search results when clicking outside the search select
 */
function onClickOutside(): void {
    closeResults();
}

// #endregion

// #region open/close results on escape key press

const hasPressedEscape = window.Vue.ref(false); // to determine if the escape key has just been pressed

/**
 * To close the search results when the escape key is pressed
 */
function onEscapeInput(): void {
    hasPressedEscape.value = true;
}

/**
 * to open the search results again whenever the query changes
 */
window.Vue.watch(
    () => query.value,
    () => {
        hasPressedEscape.value = false;
    },
);

/**
 * Whether or not to show the search results
 */
const showResults = window.Vue.computed(() => {
    if (hasPressedEscape.value) {
        return false;
    }
    if (isActive.value) {
        return true;
    }
    if (props.defaultResults && props.defaultResults.length > 0) {
        return true;
    }
    return false;
});

// #endregion

/**
 * A selected item name has been selected
 * @param item - the items selected
 */
function onSelect(item: SearchSelectItem): void {
    emit("select", item);
}

/**
 * To notify of the button being selected
 */
function onButtonSelected(): void {
    emit("buttonSelect");
}
</script>

<template>
    <div ref="searchSelectEl" v-on-click-outside="onClickOutside">
        <label :for="props.labelId" :class="`${props.isRequired ? 'required' : ''}`">{{ props.label }}</label>
        <slot name="meta"></slot>
        <label v-if="props.hasError" class="error" :for="props.labelId">{{ errorMessage }}</label>
        <ss-selected
            :selected="selected"
            :form-value-name="props.formValueName"
            :is-single-select="props.isSingleSelect"
            :can-select-item="props.canSelectItem"
            :is-read-only="props.isReadOnly"
            @select="onSelect"
            @remove="onRemove"
        />
        <div class="input-group f-no-margins">
            <ss-query-input
                v-if="!props.isReadOnly"
                v-model:query="query"
                :label-id="props.labelId"
                :placeholder="props.placeholder"
                :show-results="showResults"
                @escape="onEscapeInput"
            />
            <button
                v-if="props.hasButton"
                type="button"
                :class="`button ${props.buttonIcon}`"
                @click.prevent="onButtonSelected"
            >
                {{ props.buttonTitle }}
            </button>
        </div>
        <div class="results-container">
            <ss-results
                v-if="showResults"
                :query="query"
                :is-active="isActive"
                :has-pressed-escape="hasPressedEscape"
                :is-searching="isSearching"
                :allow-partial-matches="props.allowPartialMatches"
                :is-single-select="props.isSingleSelect"
                :results="currentResults"
                :has-no-results="hasNoResults"
                :has-error="hasError"
                :selected="selected"
                @add="onAdd"
                @remove="onRemove"
            />
        </div>
    </div>
</template>

<style lang="scss" scoped>
.results-container {
    position: relative;
    top: -1px; // to align the results with the bottom of the ss-query-input
}
</style>
