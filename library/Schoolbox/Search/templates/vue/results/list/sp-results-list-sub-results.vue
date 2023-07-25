<script setup lang="ts">
/**
 * To display all linked sub results within a result
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import { Results } from "../../../js/types/Results";
import { Result } from "../../../js/types/Result";
import { useStore } from "../../store/searchPageStore";
import { useResultsStore, SUB_RESULT_LIMIT } from "../store/spResultsStore";
import { useHasMoreResults } from "../composable/useHasMoreResults";
import SpResultsError from "../sp-results-error.vue";
import SpResultsListSubResult from "./sp-results-list-sub-result.vue";

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();

const isLoading = window.Vue.ref(false); // to notify of loading
const hasLoadError = window.Vue.ref(false); // to notify of a load error
const moreResultsCount = window.Vue.ref(0); // number of subsequent result sets that have been loaded
const hasLoadedAllResults = window.Vue.ref(false); // to determine whether all results have been loaded

/**
 * To show that there are more results that can be loaded
 * @returns {boolean}
 */
const { hasMoreResults } = useHasMoreResults(
    hasLoadedAllResults,
    window.Vue.ref(props.result._subResults?.data),
    moreResultsCount,
    SUB_RESULT_LIMIT
);

/**
 * To show the load more button when appropriate
 * @returns {boolean}
 */
const showLoadMore = window.Vue.computed(() => {
    return !isLoading.value && !hasLoadError.value && hasMoreResults.value;
});

/**
 * To collect all the params to send to the search api
 * @returns {string} - the query params
 */
function getEndpointWithParams() {
    const newQueryParams = useStore().getQueryParams();
    newQueryParams.append("cursor", props.result._subResults?.metadata?.cursor.next as string);
    const endpointWithParams = `${props.result._links.subResults as string}?${newQueryParams.toString()}`;
    return endpointWithParams;
}

/**
 * load more sub results
 */
async function loadMore() {
    isLoading.value = true;
    try {
        const response = await fetch(getEndpointWithParams(), {
            headers: {
                "Content-Type": "application/json",
            },
        });
        const results = (await response.json()) as Results;
        if (results.data.length === 0) {
            hasLoadedAllResults.value = true;
        } else {
            useResultsStore().setMoreSubResults(results.data, props.result.id);
            moreResultsCount.value += 1;
            // check if we have loaded all results yet
            if (props.result._subResults?.data.length < SUB_RESULT_LIMIT * (moreResultsCount.value + 1)) {
                hasLoadedAllResults.value = true;
            }
        }
    } catch (error) {
        hasLoadError.value = true;
    } finally {
        isLoading.value = false;
    }
}

/**
 * Load more sub results when the button is selected
 */
async function onLoadMoreSelected() {
    if (isLoading.value) {
        return;
    }
    await loadMore();
}
</script>

<template>
    <sp-results-list-sub-result
        v-for="subResult in props.result._subResults?.data"
        :key="subResult.id"
        :sub-result="subResult"
    />
    <div class="load-more-container">
        <loading-table v-if="isLoading" :rows="2" />
        <sp-results-error v-else-if="hasLoadError" />
        <button v-else-if="showLoadMore" @click.prevent="onLoadMoreSelected">Load more sub results</button>
    </div>
</template>

<style scoped>
.load-more-container {
    margin-left: 1rem;
}
</style>
