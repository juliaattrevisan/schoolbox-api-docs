<script setup lang="ts">
/**
 * A modal to search and select items
 */

import { useTemplateRef } from "vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useSelected } from "@schoolboxVue/composables/selected/useSelected";
import { useSearch } from "@schoolboxVue/composables/search/useSearch";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
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
}
const props = withDefaults(defineProps<Props>(), {
    placeholder: "Search...",
});

const emit = defineEmits<{
    searchResponse: [json: unknown];
}>();

const isOpen = defineModel<boolean>("is-open", { default: false }); // to open/close the modal

const searchSelectEl = useTemplateRef<HTMLDivElement | null>("searchSelectEl");

const selected = defineModel<SearchSelectItem[]>("selected", { default: [] }); // to store selected items
const { add, remove, update, save, undo } = useSelected(selected); // to add and remove selected items

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

const { query, isActive, isSearching, hasNoResults, hasError, search } = useSearch(
    props.endpoint,
    results,
    onSearchSuccess,
    false,
); // to search for results

/**
 * To display results straight away without searching
 */
window.Vue.watch(isOpen, (newIsOpen) => {
    if (newIsOpen) {
        save();
        void search();
    }
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

/**
 * The selection has been confirmed, so close the modal
 */
function onSelect(): void {
    close();
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
                            :results="results"
                            :has-no-results="hasNoResults"
                            :has-error="hasError"
                            :selected="selected"
                            @add="onAdd"
                            @remove="onRemove"
                        />
                    </div>
                </div>
                <ssm-footer @cancel="onCancel" @select="onSelect" />
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
