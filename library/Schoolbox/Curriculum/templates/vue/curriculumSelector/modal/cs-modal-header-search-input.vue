<script setup lang="ts">
/**
 * To search the currently selected curriculum node
 */

import debounce from "lodash/debounce";
import { useModalSearchStore, DEBOUNCE_AMOUNT } from "../store/csModalSearchStore";
import { useModalViewNodesStore } from "../store/csModalViewNodesStore";

interface Props {
    isDisabled: boolean; // to disable certain events when editing is taking place
    includeWithoutCode: boolean; // set to true if curriculum nodes with blank codes need to be selectable
}
const props = defineProps<Props>();

const modalSearchStore = useModalSearchStore();
const modalViewNodesStore = useModalViewNodesStore();

/**
 * Only enable search once a first node is selected
 * @returns {boolean} - whether the input is disabled or not
 */
const isDisabled = window.Vue.computed(() => !modalViewNodesStore.isActive || props.isDisabled);

/**
 * Only enable search once a first node is selected
 * @returns {string} - the placeholder text
 */
const placeholder = window.Vue.computed(() => {
    if (!modalViewNodesStore.isActive) {
        return "Expand a curriculum to enable search...";
    }
    return `Search for codes or keywords...`;
});

/**
 * debounce to avoid constant polling while the user is typing
 */
const debounceSearch = debounce(() => {
    modalSearchStore.includeWithoutCode = props.includeWithoutCode;
    void modalSearchStore.search();
}, DEBOUNCE_AMOUNT);

/**
 * start searching once the search is active, watching the query string for input
 */
window.Vue.watch(
    () => modalSearchStore.query,
    () => {
        if (modalSearchStore.isActive) {
            modalSearchStore.enableSearch();
            debounceSearch();
        }
    },
);

/**
 * Clear the current search query
 */
function onClearSearchSelected(): void {
    modalSearchStore.clear();
}
</script>

<template>
    <div class="input-group">
        <input
            ref="queryInputEl"
            v-model="modalSearchStore.query"
            type="text"
            class="search-input"
            :disabled="isDisabled"
            :placeholder="placeholder"
            @input="
                modalSearchStore.query = ($event.target as HTMLInputElement).value /* Fix Android events SA-12170 */
            "
        />
        <button
            v-if="modalSearchStore.query !== ''"
            title="Clear the current search"
            class="button icon-close"
            :disabled="isDisabled"
            @click.prevent="onClearSearchSelected"
        >
            Clear
        </button>
    </div>
</template>
