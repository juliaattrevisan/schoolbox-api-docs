<script setup lang="ts">
/**
 * To search the currently selected curriculum node
 */

import debounce from "lodash/debounce";
import { useViewNodesStore } from "../viewNodes/store/mcViewNodesStore";
import { useSearchStore, DEBOUNCE_AMOUNT } from "../store/mcSearchStore";

const props = defineProps({
    /**
     * to disable certain events when editing is taking place
     */
    isDisabled: {
        type: Boolean,
        required: true,
    },
});

const viewNodesStore = useViewNodesStore();
const searchStore = useSearchStore();

/**
 * Only enable search once a first node is selected
 *
 * @returns { Boolean }
 */
const isDisabled = window.Vue.computed(() => !viewNodesStore.isActive || props.isDisabled);

/**
 * Only enable search once a first node is selected
 *
 * @returns { String }
 */
const placeholder = window.Vue.computed(() => {
    if (!viewNodesStore.isActive) {
        return "Expand a curriculum to enable search...";
    } else {
        return `Search for codes or keywords...`;
    }
});

/**
 * start searching once the search is active, watching the query string for input
 */
window.Vue.watch(
    () => searchStore.query,
    () => {
        if (searchStore.isActive) {
            searchStore.enableSearch();
            debounceSearch();
        }
    },
);

/**
 * debounce to avoid constant polling while the user is typing
 */
const debounceSearch = debounce(() => {
    searchStore.search();
}, DEBOUNCE_AMOUNT);

/**
 * Clear the current search query
 */
function onClearSearchSelected() {
    searchStore.clear();
}
</script>

<template>
    <div class="medium-8 small-12 columns">
        <label for="search">Search</label>
        <div class="input-group">
            <input
                ref="queryInputEl"
                v-model="searchStore.query"
                type="text"
                :disabled="isDisabled"
                :placeholder="placeholder"
                data-test-search-input
                @input="searchStore.query = ($event.target as HTMLInputElement).value /* Fix Android events SA-12170 */"
            />
            <button
                v-if="searchStore.query !== ''"
                title="Clear the current search"
                class="button icon-close"
                :disabled="isDisabled"
                data-test-clear-search
                @click.prevent="onClearSearchSelected"
            >
                Clear
            </button>
        </div>
    </div>
    <div class="medium-4 small-12 columns">
        <label class="inline-block">Show disabled outcomes</label>
        <div class="long switch">
            <input
                id="show-only-enabled"
                type="checkbox"
                v-model="viewNodesStore.showDisabled"
                data-test-show-only-enabled
                :disabled="props.isDisabled"
            />
            <label for="show-only-enabled">
                <span>Shown</span>
                <span>Hidden</span>
            </label>
        </div>
    </div>
</template>

<style scoped></style>
