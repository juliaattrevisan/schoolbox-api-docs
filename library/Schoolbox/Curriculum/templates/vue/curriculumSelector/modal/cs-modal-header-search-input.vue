<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To search the currently selected curriculum node
 */

import debounce from "lodash/debounce";
import { useModalSearchStore, DEBOUNCE_AMOUNT } from "../store/csModalSearchStore";
import { useModalViewNodesStore } from "../store/csModalViewNodesStore";

const props = defineProps({
    /**
     * to disable certain events when editing is taking place
     */
    isDisabled: {
        type: Boolean,
        required: true,
    },
});

const modalSearchStore = useModalSearchStore();
const modalViewNodesStore = useModalViewNodesStore();

/**
 * Only enable search once a first node is selected
 *
 * @returns { Boolean }
 */
const isDisabled = window.Vue.computed(() => !modalViewNodesStore.isActive || props.isDisabled);

/**
 * Only enable search once a first node is selected
 *
 * @returns { String }
 */
const placeholder = window.Vue.computed(() => {
    if (!modalViewNodesStore.isActive) {
        return "Expand a curriculum to enable search...";
    } else {
        return `Search for codes or keywords...`;
    }
});

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
 * debounce to avoid constant polling while the user is typing
 */
const debounceSearch = debounce(() => {
    modalSearchStore.search();
}, DEBOUNCE_AMOUNT);

/**
 * Clear the current search query
 */
function onClearSearchSelected() {
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
            @input="modalSearchStore.query = $event.target.value /* Fix Android events SA-12170 */"
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
