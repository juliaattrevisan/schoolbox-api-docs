<script setup lang="ts">
/**
 * A modal to search and select items
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useSelected } from "@schoolboxVue/composables/selected/useSelected";
import { useSearch } from "@schoolboxVue/composables/search/useSearch";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import SsResults from "../ss-results.vue";
import SsmFooter from "./ssm-footer.vue";

interface Props {
    title: string; // the title of the modal
    endpoint: string; // the search api endpoint
    isSingleSelect?: boolean; // whether only one result can be selected
    allowPartialMatches?: boolean; // whether to allow partial matches, so can match part of a word
}
const props = defineProps<Props>();

const isOpen = defineModel<boolean>("is-open", { default: false }); // to open/close the modal

const selected = defineModel<SearchSelectItem[]>("selected", { default: [] }); // to store selected items
const { add, remove, update, save, undo } = useSelected(selected); // to add and remove selected items

const results = defineModel<SearchSelectItem[]>("results", { default: [] }); // to store search results
const { query, isActive, isSearching, hasNoResults, hasError, search } = useSearch(props.endpoint, results, true); // to search for results

/**
 * To display results without searching
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
 * Close the modal, also clearing any selected results
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
 * and insert into the current selection
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
                <ssm-footer @cancel="onCancel" @select="onSelect" />
            </div>
        </basic-modal>
    </div>
</template>

<style scoped></style>
