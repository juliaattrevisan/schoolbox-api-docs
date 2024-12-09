<script setup lang="ts">
/**
 * To load more selected curriculum
 */

import { useLoadMoreStore } from "./store/ccLoadMoreStore";

const loadMoreStore = useLoadMoreStore();

/**
 * To show the load more button when appropriate
 * @returns {boolean}
 */
const showLoadMore = window.Vue.computed(() => {
    return !loadMoreStore.isLoading && !loadMoreStore.hasLoadError && loadMoreStore.hasMore;
});

/**
 * Load more nodes
 */
async function onLoadMoreSelected(): Promise<void> {
    if (loadMoreStore.isLoading) {
        return;
    }
    await loadMoreStore.report();
}
</script>

<template>
    <div v-if="loadMoreStore.isLoading" class="loader"></div>
    <div v-else-if="loadMoreStore.hasLoadError" class="c-island l-pad-m">
        <div class="alert-box alert f-no-margins">
            Sorry, there was an error during report retrieval. Please
            <a href="" title="refresh the page"> refresh the page </a>
            to try again and contact your support if the problem persists.
            <template v-if="loadMoreStore.loadError"
                ><em>Further support info: {{ loadMoreStore.loadError }}</em></template
            >
        </div>
    </div>
    <button v-else-if="showLoadMore" data-test="cc-load-more-button" @click.prevent="onLoadMoreSelected">
        Load more curriculum
    </button>
</template>

<style scoped></style>
