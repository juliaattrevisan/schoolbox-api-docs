<script setup lang="ts">
/**
 * To indicate the status of the search, whether results are being shown
 * or there's no results to be found
 */

import { useResultsStore } from "./store/spResultsStore";
import { useQueryStore } from "../store/spQueryStore";
import { useSearchStore } from "../store/spSearchStore";

const resultsStore = useResultsStore();
const queryStore = useQueryStore();
const searchStore = useSearchStore();

/**
 * We should only show the header if we have searched, the search has completed and there's no error
 * @returns {boolean}
 */
const showHeader = window.Vue.computed(() => {
    return !searchStore.hasNotSearched;
});

/**
 * To show that no results match the current search
 * @returns {boolean}
 */
const titleStart = window.Vue.computed(() => {
    if (searchStore.isSearching) {
        return "Searching for";
    }
    if (resultsStore.hasResults) {
        return "Showing";
    }
    return "No";
});

/**
 * To show that no results match the current search
 * @returns {boolean}
 */
const title = window.Vue.computed(() => {
    return `${titleStart.value} results for <em>${queryStore.activeQuery}</em>`;
});
</script>

<template>
    <template v-if="showHeader">
        <!-- eslint-disable-next-line vue/no-v-html -->
        <h1 class="header l-mar--0" data-test-sp-results-header v-html="title"></h1>
    </template>
</template>

<style scoped></style>
