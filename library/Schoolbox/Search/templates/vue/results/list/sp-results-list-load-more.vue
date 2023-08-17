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
async function onLoadMoreSelected() {
    if (loadMoreResultsStore.isLoading) {
        return;
    }
    await loadMoreResultsStore.loadMore();
}
</script>

<template>
    <loading-table v-if="loadMoreResultsStore.isLoading" :rows="2" />
    <sp-results-error v-else-if="loadMoreResultsStore.hasLoadError" />
    <button v-else-if="showLoadMore" data-sp-results-list-load-more-button @click.prevent="onLoadMoreSelected">
        Load more results
    </button>
    <div v-else-if="resultsStore.hasLoadedAllResults" class="alert-box" data-sp-results-list-load-more-all-loaded>
        All results have been loaded
    </div>
</template>

<style scoped></style>
