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
let autoClickInterruptRequested = false;

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
    await loadMoreResultsStore.loadMore(null);
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
    async (newVal) => {
        if (
            newVal.allResultsLength < newVal.resultPageSize &&
            newVal.hasMoreResults &&
            !newVal.isLoading &&
            !newVal.hasLoadError
        ) {
            // We don't yet have a full page of results, and more results exist.
            if (autoClickInterruptRequested) {
                // Do not simulate a click the 'Load more results' button, but reset autoClickInterruptRequested.
                autoClickInterruptRequested = false;
            } else {
                // Simulate a click the 'Load more results' button, and note we are now auto-clicking 'Load more results'.
                loadMoreResultsStore.autoClickLoadMoreResults = true;
                if (loadMoreResultsStore.isLoading) {
                    return;
                }
                // Limit the number of records searched for to the number needed to fill the page.
                await loadMoreResultsStore.loadMore(newVal.resultPageSize - newVal.allResultsLength);
            }
        }
    },
    { immediate: true },
);

/**
 * Interrupt auto-clicking so that there will be no more automatic clicks on the 'Load more results' button.
 */
function onAutoClickInterruptRequested(): void {
    autoClickInterruptRequested = true;
    loadMoreResultsStore.autoClickLoadMoreResults = false;
}
</script>

<template>
    <loading-cards v-if="loadMoreResultsStore.isLoading" :rows="2" />
    <div v-if="loadMoreResultsStore.autoClickLoadMoreResults">
        <button
            title="This just means automatically clicking 'Load more results' until either a full page of results has been found, or all records have been searched."
            @click.prevent="onAutoClickInterruptRequested"
        >
            Searching for more results... (Click to cancel)
        </button>
    </div>
    <sp-results-error v-else-if="loadMoreResultsStore.hasLoadError" />
    <button v-else-if="showLoadMore" data-test="sp-results-load-more-button" @click.prevent="onLoadMoreSelected">
        Load more results
    </button>
    <div v-else-if="resultsStore.hasLoadedAllResults" class="alert-box" data-test="sp-results-load-more-all-loaded">
        All results have been loaded
    </div>
</template>

<style scoped></style>
