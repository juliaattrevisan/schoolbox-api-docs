<script setup lang="ts">
/**
 * To load more results when available, adding to the existing results
 */

import LoadingCards from "@schoolboxVue/components/loading/loading-cards.vue";
import SpResultsError from "./sp-results-error.vue";
import { useStore } from "../store/searchPageStore";
import { useResultsStore } from "./store/spResultsStore";
import { useLoadMoreResultsStore } from "./store/spLoadMoreResultsStore";

const store = useStore();
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
async function onLoadMoreSelected(): Promise<void> {
    if (loadMoreResultsStore.isLoading) {
        return;
    }
    await loadMoreResultsStore.loadMore();
}

/**
 * Watch for conditions under which more results should be fetched automatically.
 * If those conditions are met, fetch more results by simulating a click on the 'Load more results' button.
 */
window.Vue.watch(
    () => ({
        allResultsLength: resultsStore.allResults.length,
        resultPageSize: store.resultPageSize,
        hasMoreResults: resultsStore.hasMoreResults,
        isLoading: loadMoreResultsStore.isLoading,
        hasLoadError: loadMoreResultsStore.hasLoadError,
    }),
    (newVal) => {
        if (
            newVal.allResultsLength < newVal.resultPageSize &&
            newVal.hasMoreResults &&
            !newVal.isLoading &&
            !newVal.hasLoadError
        ) {
            // We don't yet have a full page of results, but more results exist.
            // So simulate a click on the 'Load more results' button.
            void onLoadMoreSelected();
        }
    },
    { immediate: true },
);
</script>

<template>
    <loading-cards v-if="loadMoreResultsStore.isLoading" :rows="2" />
    <sp-results-error v-else-if="loadMoreResultsStore.hasLoadError" />
    <button v-else-if="showLoadMore" data-test="sp-results-load-more-button" @click.prevent="onLoadMoreSelected">
        Load more results
    </button>
    <div v-else-if="resultsStore.hasLoadedAllResults" class="alert-box" data-test="sp-results-load-more-all-loaded">
        All results have been loaded
    </div>
</template>

<style scoped></style>
