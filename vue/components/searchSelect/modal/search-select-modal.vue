<script setup lang="ts">
/**
 * A modal to search and select items
 */

import { toRef, useTemplateRef } from "vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useSelected } from "@schoolboxVue/composables/selected/useSelected";
import { useQuerySearch } from "@schoolboxVue/composables/search/useQuerySearch";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import { useOnSelectedChange } from "../composables/useOnSelectedChange";
import SsQueryInput from "../ss-query-input.vue";
import SsResults from "../ss-results.vue";
import SsmFooter from "./ssm-footer.vue";

interface Props {
    label: string; // the label text
    labelId: string; // the label id
    title: string; // the title of the modal
    endpoint: string; // the search api endpoint
    manageSearchResponse?: boolean; // whether to manage the response internally, or dispatch an emit to manage it externally
    isSingleSelect?: boolean; // whether only one result can be selected
    allowPartialMatches?: boolean; // whether to allow partial matches, so can match part of a word
    placeholder?: string; // the query input placeholder text
    defaultResults?: SearchSelectItem[]; // show selection options when the search input is empty
}
const props = withDefaults(defineProps<Props>(), {
    placeholder: "Search...",
    defaultResults: undefined,
});

const emit = defineEmits<{
    searchResponse: [json: unknown, isDefaultResults: boolean];
    nextSearchResponse: [json: unknown, isDefaultResults: boolean];
}>();

const isOpen = defineModel<boolean>("is-open", { default: false }); // to open/close the modal

const searchSelectEl = useTemplateRef<HTMLDivElement | null>("searchSelectEl");

const selected = defineModel<SearchSelectItem[]>("selected", { default: [] }); // to store selected items
const { add, remove, update, save, undo, savedSelected } = useSelected(selected); // to add and remove selected items
const { onAdd, onRemove } = useOnSelectedChange(
    toRef(() => props.isSingleSelect),
    selected,
    add,
    remove,
    update,
); // to handle selected item changes picked up within the <template/>

const results = defineModel<SearchSelectItem[]>("results", { default: [] }); // to store search results
const nextResultsCursor = window.Vue.ref<string | null>(null); // to store the next cursor for the next search
/**
 * To handle the search response, we handle some basic responses internally,
 * but if the response is more complex, we emit the response to be handled externally
 * @param json - the returned json
 * @param isActive - whether the search is active
 * @param isNextResults - whether the results are the next set of results
 */
function onSearchSuccess(json: unknown, isActive: boolean, isNextResults: boolean = false): void {
    // check for next cursor at metadata.cursor.next
    if (typeof json === "object" && json !== null && "metadata" in json) {
        nextResultsCursor.value = (json as { metadata: { cursor: { next: string } } }).metadata.cursor.next;
    }
    if (isNextResults && props.manageSearchResponse) {
        emit("nextSearchResponse", json, isActive);
        return;
    }
    // emit to handle the response externally, as the response object is typically something not handled by the the basic structure below
    if (props.manageSearchResponse) {
        emit("searchResponse", json, isActive);
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
    results,
    onSearchSuccess,
    false,
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

/**
 * Rest the modal state
 */
function close(): void {
    isOpen.value = false;
    query.value = "";
}

/**
 * Close the modal, also clearing any updated selected
 */
function onCancel(): void {
    undo();
    close();
}

/**
 * The selection has been confirmed, so close the modal
 */
function onSelect(): void {
    close();
}

const defaultResultsUrl = window.Vue.ref(`${props.endpoint}query=`); // query is currently required to due bug: https://schoolbox.atlassian.net/browse/SA-18815
const hasLoadedDefaultResults = window.Vue.ref(false); // to track if the default results have been loaded

/**
 * Load more results if there's more to load
 * @param isMoreResults - whether this is more results to load
 */
async function loadDefaultResults(isMoreResults: boolean): Promise<void> {
    try {
        if (!isMoreResults) {
            // only show the initial loading on the first result load
            isSearching.value = true;
        }
        const response = await fetch(defaultResultsUrl.value, {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
            },
        });
        if (!response.ok) {
            hasError.value = true;
            return;
        }
        const json: unknown = await response.json();
        onSearchSuccess(json, isActive.value, isMoreResults);
    } catch (error) {
        console.error(error);
        hasError.value = true;
    } finally {
        isSearching.value = false;
    }
}

/**
 * To save results in case of cancellation and
 * to display default results straight away without searching
 */
window.Vue.watch(isOpen, async (newIsOpen) => {
    if (newIsOpen) {
        save();
        if (hasLoadedDefaultResults.value) {
            return;
        }
        await loadDefaultResults(false);
        hasLoadedDefaultResults.value = true; // to prevent loading default results again
    }
});

/**
 * To load more results when the user scrolls to the bottom of the results container
 */
function onAtResultsBottom(): void {
    if (nextResultsCursor.value) {
        defaultResultsUrl.value = `${props.endpoint}query=&cursor=${nextResultsCursor.value}`;
        void loadDefaultResults(true);
    }
}
</script>

<template>
    <div>
        <basic-modal v-model:is-open="isOpen">
            <div class="l-flex-col l-gap-m">
                <h2 class="f-no-margins">{{ props.title }}</h2>
                <div class="f-no-margins">
                    <label :for="props.labelId">{{ props.label }}</label>
                    <ss-query-input
                        v-model:query="query"
                        :label-id="props.labelId"
                        :parent-el="searchSelectEl"
                        :placeholder="props.placeholder"
                    />
                    <div class="results-container">
                        <ss-results
                            :query="query"
                            :is-active="isActive"
                            :is-searching="isSearching"
                            :allow-partial-matches="props.allowPartialMatches"
                            :is-single-select="props.isSingleSelect"
                            :results="currentResults"
                            :has-no-results="hasNoResults"
                            :has-error="hasError"
                            :selected="selected"
                            @add="onAdd"
                            @remove="onRemove"
                            @at-bottom="onAtResultsBottom"
                        />
                    </div>
                </div>
                <ssm-footer :saved-selected="savedSelected" @cancel="onCancel" @select="onSelect" />
            </div>
        </basic-modal>
    </div>
</template>

<style lang="scss" scoped>
.results-container {
    position: relative;
    top: -1px; // to align the results with the bottom of the ss-query-input
}
</style>
