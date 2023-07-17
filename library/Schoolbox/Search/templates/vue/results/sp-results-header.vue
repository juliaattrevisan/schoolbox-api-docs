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
    return !searchStore.hasNotSearched && !searchStore.isSearching && !searchStore.hasLoadError;
});

/**
 * To show that no results match the current search
 * @returns {boolean}
 */
const title = window.Vue.computed(() => {
    const showing = resultsStore.hasResults ? "Showing" : "No";
    return `${showing} results for <strong>${queryStore.activeQuery}</strong>`;
});
</script>

<template>
    <template v-if="showHeader">
        <h1 class="header" v-html="title"></h1>
    </template>
</template>

<style scoped></style>
