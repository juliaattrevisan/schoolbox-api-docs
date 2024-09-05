<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To list all the current search results, also include a select all
 * button to easily add/remove all search results
 */

import { gsap } from "gsap/all";
import { useSearchStore } from "./store/csSearchStore";
import CsSelectAllSearchResults from "./cs-select-all-search-results.vue";
import CsSearchResult from "./cs-search-result.vue";

const searchStore = useSearchStore();
const searchResultsContainerEl = window.Vue.ref(null);
const searchResultsEl = window.Vue.ref(null);

const props = defineProps({
    /**
     * so can only select a single node
     */
    isSingleSelect: {
        type: Boolean,
        required: false,
        default: false,
    },
});

/**
 * show select all option for adding / removing codes only if there's
 * single select is not active and more than one search result is available
 *
 * @returns { Boolean }
 */
const showSelectAll = window.Vue.computed(() => {
    return !props.isSingleSelect && searchStore.filteredResults.length > 1;
});

/**
 * to slide up and down the search results
 */
window.Vue.watch(
    () => searchStore.filteredResults,
    () => {
        window.Vue.nextTick(() => {
            const targetHeight = searchResultsEl.value.offsetHeight;
            gsap.to(searchResultsContainerEl.value, {
                duration: 0.3,
                height: targetHeight,
            });
        });
    },
);
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
                        <cs-select-all-search-results />
                    </li>
                    <li v-for="result in searchStore.filteredResults" :key="result.id">
                        <cs-search-result :result="result" :is-single-select="props.isSingleSelect" />
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
    position: relative;
    overflow: hidden;
}

.search-results {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    max-height: 400px;
    overflow: auto;
    width: 100%;
    z-index: var(--sbx-depth-pop-over);
    ul {
        list-style: none;
        margin: 0;
        transition: height 0.3s;
        li {
            border-bottom: 1px solid var(--body-background);
            border-left: 1px solid var(--navigation-hover);
            border-right: 1px solid var(--navigation-hover);
            transform: none !important;
            &:first-child {
                border-top: 1px solid var(--navigation-hover);
            }
            &:last-child {
                border-bottom: 1px solid var(--navigation-hover);
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
