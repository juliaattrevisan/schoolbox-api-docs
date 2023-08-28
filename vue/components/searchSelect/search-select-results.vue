<script setup lang="ts">
/**
 *
 */

import gsap from "gsap";
import type { Result } from "./types/Result";
import SearchSelectSelectAll from "./search-select-select-all.vue";
import SearchSelectResult from "./search-select-result.vue";

interface Props {
    query: string; // the search query
    isActive: boolean; // whether to show the search results
    isSearching: boolean; // to notify of searching
    isSingleSelect: boolean; // whether only one result can be selected
    results: Result[]; // the results returned from the search
    selected: Result[]; // the selected results
}

const props = defineProps<Props>();

const emit = defineEmits<{
    select: [result: Result];
}>();

const searchResultsContainerEl = window.Vue.ref<HTMLDivElement | null>(null);
const searchResultsEl = window.Vue.ref<HTMLDivElement | null>(null);

/**
 * to slide up and down the search results
 */
// window.Vue.watch(
//     () => props.results,
//     async () => {
//         await window.Vue.nextTick(() => {
//             const targetHeight = (searchResultsEl.value as HTMLDivElement).offsetHeight;
//             gsap.to(searchResultsContainerEl.value, {
//                 duration: 0.3,
//                 height: targetHeight,
//             });
//         });
//     }
// );

/**
 * show select all option for adding / removing results only if there's
 * more than one search result and only a single result can't be selected
 * @returns {boolean} - whether to show the select all option
 */
const showSelectAll = window.Vue.computed(() => {
    if (props.isSingleSelect) {
        return false;
    }
    if (props.results.length <= 1) {
        return false;
    }
    return true;
});

/**
 * To toggle the selected state of the result
 * @param {Result} result - the result selected
 */
function onSelected(result: Result) {
    emit("select", result);
}
</script>

<template>
    <div v-if="isActive" ref="searchResultsContainerEl" class="search-results-container">
        <div ref="searchResultsEl" class="search-results">
            <ul>
                <li v-if="props.isSearching" class="search-status">Searching for results....</li>
                <li v-else-if="props.results.length === 0" class="search-status">
                    No results found for your current search.
                </li>
                <template v-else>
                    <li v-if="showSelectAll">
                        <search-select-select-all />
                    </li>
                    <li v-for="result in props.results" :key="result.id">
                        <search-select-result
                            :query="query"
                            :selected="selected"
                            :results="props.results"
                            :result="result"
                            @select="onSelected"
                        />
                    </li>
                </template>
            </ul>
        </div>
        <!-- .search-results -->
    </div>
    <!-- .search-results-container -->
</template>

<style lang="scss" scoped>
// .search-results-container {
//     height: 0; //will animate the results within with the window.Vue.watch() above
//     position: relative;
//     overflow: hidden;
// }

.search-results {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    max-height: 400px;
    overflow: auto;
    width: 100%;
    z-index: 10;
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
