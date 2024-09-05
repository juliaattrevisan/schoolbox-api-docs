<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To list all the current search results, also include a select all
 * button to easily add/remove all search results
 */

import { gsap } from "gsap/all";
import { useSearchStore } from "../store/mgSearchStore";
import MgSelectAllSearchResults from "./mg-select-all-search-results.vue";
import MgAddMembersSearchResult from "./mg-search-result.vue";

const searchStore = useSearchStore();
const searchResultsContainerEl = window.Vue.ref(null);
const searchResultsEl = window.Vue.ref(null);

/**
 * show select all option for adding / removing codes only if there's
 * more than one search result
 *
 * @returns { Boolean }
 */
const showSelectAll = window.Vue.computed(() => {
    return searchStore.filteredResults.length > 1;
});

/**
 * so can slide down to show searching
 */
window.Vue.watch(
    () => searchStore.isLoading,
    () => {
        window.Vue.nextTick(() => animate());
    },
);

/**
 * to slide up and down based on the amount of results
 */
window.Vue.watch(
    () => searchStore.filteredResults,
    () => {
        window.Vue.nextTick(() => animate());
    },
);

/**
 * to slide up and down the search results
 */
function animate() {
    //the height will be 2 as there's a 2px border, and we add +1 to allow the border to be shown
    const targetHeight = searchResultsEl.value.offsetHeight === 2 ? 0 : searchResultsEl.value.offsetHeight + 1;
    gsap.to(searchResultsContainerEl.value, {
        duration: 0.3,
        height: targetHeight,
    });
}
</script>

<template>
    <div class="search-results-container" ref="searchResultsContainerEl">
        <div class="search-results" ref="searchResultsEl">
            <ul>
                <li v-if="searchStore.isLoading" class="search-status">Searching for results....</li>
                <li v-else-if="searchStore.hasNoResults" class="search-status">
                    No results found for your current search.
                </li>
                <template v-else>
                    <li v-if="showSelectAll">
                        <mg-select-all-search-results />
                    </li>
                    <li v-for="result in searchStore.filteredResults" :key="result.id">
                        <mg-add-members-search-result :result="result" />
                    </li>
                </template>
            </ul>
        </div>
        <!-- .search-results -->
    </div>
    <!-- .search-results-container -->
</template>

<style lang="scss" scoped>
.search-results-container {
    height: 0; //will animate the results within with the window.Vue.watch() above
    overflow: hidden;
    position: relative;
}

.search-results {
    max-height: 400px;
    overflow: auto;
    width: 100%;
    ul {
        border: 1px solid var(--navigation-hover);
        list-style: none;
        margin: 0;
        li {
            border-bottom: 1px solid var(--body-background);
            transform: none !important;
            &:last-child {
                border-bottom: none;
            }
        }
    }
}

.search-status {
    background-color: #fafafa;
    padding: 0.75rem;
    font-style: italic;
}
</style>
