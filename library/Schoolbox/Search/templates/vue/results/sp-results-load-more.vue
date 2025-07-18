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
 * This determines whether more results should be fetched automatically.
 */
const shouldAutoLoad = window.Vue.computed(() => {
    return (
        resultsStore.totalResultsCount < store.resultPageSize &&
        resultsStore.hasMoreResults &&
        !loadMoreResultsStore.isLoading &&
        !loadMoreResultsStore.hasLoadError
    );
});

/**
 * Watch for conditions under which more results should be fetched automatically.
 * If those conditions are met, fetch more results by simulating a click on the 'Load more results' button.
 */
window.Vue.watch(
    shouldAutoLoad,
    async (newVal) => {
        if (!newVal) {
            return;
        }

        // The shouldAutoLoad condition has been met, so we don't yet have a full page of results, and more results exist.

        if (autoClickInterruptRequested) {
            // Do not simulate a click the 'Load more results' button, but reset autoClickInterruptRequested.
            autoClickInterruptRequested = false;
            return;
        }

        // Simulate a click the 'Load more results' button, and note we are now auto-clicking 'Load more results'.
        loadMoreResultsStore.autoClickLoadMoreResults = true;
        if (loadMoreResultsStore.isLoading) {
            return;
        }
        // Limit the number of records searched for to the number needed to fill the page.
        await loadMoreResultsStore.loadMore(store.resultPageSize - resultsStore.totalResultsCount);
    },
    { immediate: true },
);

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
 * Interrupt auto-clicking so that there will be no more automatic clicks on the 'Load more results' button.
 */
function onAutoClickInterruptRequested(): void {
    autoClickInterruptRequested = true;
    loadMoreResultsStore.autoClickLoadMoreResults = false;
}
</script>

<template>
    <loading-cards v-if="loadMoreResultsStore.isLoading" :rows="2" />
    <button
        v-if="loadMoreResultsStore.autoClickLoadMoreResults"
        title="The search will continue until the page is full or all items are found."
        @click.prevent="onAutoClickInterruptRequested"
    >
        Cancel ongoing search
    </button>
    <sp-results-error v-else-if="loadMoreResultsStore.hasLoadError" />
    <button v-else-if="showLoadMore" data-test="sp-results-load-more-button" @click.prevent="onLoadMoreSelected">
        Load more results
    </button>
    <div v-else-if="resultsStore.hasLoadedAllResults" class="alert-box" data-test="sp-results-load-more-all-loaded">
        All results have been loaded
    </div>
</template>

<style scoped></style>
