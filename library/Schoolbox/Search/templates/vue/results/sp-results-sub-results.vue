<script setup lang="ts">
/**
 * To display all linked sub results within a result and load more when available
 */

import LoadingCards from "@schoolboxVue/components/loading/loading-cards.vue";
import type { Results } from "../../js/types/Results";
import type { Result } from "../../js/types/Result";
import { useStore } from "../store/searchPageStore";
import { useResultsStore } from "./store/spResultsStore";
import { useHasMoreResults } from "./composable/useHasMoreResults";
import SpResultsError from "./sp-results-error.vue";
import SpResultsSubResult from "./sp-results-sub-result.vue";

interface Props {
    result: Result; // the result to display
    parentIndex: number; // the index of the parent result
}
const props = defineProps<Props>();

const moreResultsCount = window.Vue.ref(0); // number of subsequent result sets that have been loaded
const hasLoadedAllResults = window.Vue.ref(false); // to determine whether all results have been loaded
const url = window.Vue.ref("");
const isLoading = window.Vue.ref(false);
const hasLoadError = window.Vue.ref(false);

/**
 * To show that there are more results that can be loaded
 * @returns {boolean}
 */
const { hasMoreResults } = useHasMoreResults(hasLoadedAllResults, window.Vue.ref(props.result._subResults as Results));

/**
 * To show the load more button when appropriate
 * @returns {boolean}
 */
const showLoadMore = window.Vue.computed(() => {
    return !isLoading.value && !hasLoadError.value && hasMoreResults.value;
});

/**
 * To collect all the params to send to the search api
 * @returns - the query params
 */
function getEndpointWithParams(): string {
    const newQueryParams = useStore().getQueryParams();
    newQueryParams.append("cursor", props.result._subResults?.metadata?.cursor.next as string);
    const subResultsUrl = props.result._subResults?.metadata.url as string;
    return props.result._subResults === undefined ? "" : `${subResultsUrl}?${newQueryParams.toString()}`;
}

/**
 * load more sub results
 */
async function loadMore(): Promise<void> {
    url.value = getEndpointWithParams();
    try {
        isLoading.value = true;
        hasLoadError.value = false;
        const response = await fetch(url.value, {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
            },
        });
        const loadedResults = (await response.json()) as Results;
        if (loadedResults.data.length === 0) {
            hasLoadedAllResults.value = true;
        } else {
            useResultsStore().setMoreSubResults(loadedResults, props.parentIndex);
            moreResultsCount.value += 1;
            // If cursor.next has not been supplied with the sub-results, we have loaded all sub-results.
            if (loadedResults.metadata.cursor.next === null) {
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
async function onLoadMoreSelected(): Promise<void> {
    if (isLoading.value) {
        return;
    }
    await loadMore();
}

/**
 * To determine whether the sub result is the last one
 * @param index - the index of the sub result
 * @returns - whether the sub result is the last one
 */
function isLast(index: number): boolean {
    if (props.result._subResults === undefined) {
        return false;
    }
    return index === props.result._subResults.data.length - 1;
}
</script>

<template>
    <sp-results-sub-result
        v-for="(subResult, index) in props.result._subResults?.data"
        :key="subResult.id"
        :sub-result="subResult"
        :is-last="isLast(index)"
        :data-test="`sp-results-sub-results-result-${props.result.id}-${subResult.id}`"
    />
    <loading-cards v-if="isLoading" :rows="1" />
    <sp-results-error v-else-if="hasLoadError" />
    <button
        v-else-if="showLoadMore"
        :data-test="`sp-results-sub-results-load-more-${props.result.id}`"
        @click.prevent="onLoadMoreSelected"
    >
        Load more sub results
    </button>
</template>

<style scoped></style>
