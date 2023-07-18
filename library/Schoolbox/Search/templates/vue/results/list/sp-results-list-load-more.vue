<script setup lang="ts">
/**
 * To load more results when available, adding to the existing results
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import SpResultsError from "../sp-results-error.vue";
import { useResultsStore } from "../store/spResultsStore";
import { useLoadMoreResultsStore } from "../store/spLoadMoreResults";

const resultsStore = useResultsStore();
const loadMoreResultsStore = useLoadMoreResultsStore();

/**
 * To show the load more button when appropriate
 * @returns {boolean}
 */
const showLoadMore = window.Vue.computed(() => {
    return !loadMoreResultsStore.isLoading && !loadMoreResultsStore.hasLoadError && resultsStore.hasMoreResults;
});

/**
 * Load more results
 */
async function onLoadMore() {
    if (loadMoreResultsStore.isLoading) {
        return;
    }
    await loadMoreResultsStore.loadMore();
}
</script>

<template>
    <loading-table v-if="loadMoreResultsStore.isLoading" />
    <sp-results-error v-else-if="loadMoreResultsStore.hasLoadError" />
    <button v-else-if="showLoadMore" @click.prevent="onLoadMore">Load more results</button>
</template>

<style scoped></style>
