<script setup lang="ts">
/**
 * The search results container
 */

import gsap from "gsap";
import { onKeyStroke } from "@vueuse/core";
import type { Result } from "./types/Result";
import SearchSelectSelectAll from "./search-select-select-all.vue";
import SearchSelectResult from "./search-select-result.vue";

interface Props {
    query: string; // the search query
    isActive: boolean; // whether the search is active
    isSearching: boolean; // to notify of searching
    isSingleSelect: boolean; // whether only one result can be selected
    results: Result[]; // the results returned from the search
    hasNoResults: boolean; // whether there are no results
    selectedItems: Result[]; // the selected results
}

const props = defineProps<Props>();

const emit = defineEmits<{
    select: [result: Result]; // to select a search result
    deselect: [result: Result]; // to deselect a search result
    selectAll: []; // to select all search results
    removeAll: []; // to remove all search results
}>();

const searchResultsContainerEl = window.Vue.ref<HTMLDivElement | null>(null);
const searchResultsEl = window.Vue.ref<HTMLDivElement | null>(null);

/**
 * to animate the height of the search results container
 * @param targetHeight - the height to animate to
 */
function animateHeight(targetHeight: number): void {
    gsap.to(searchResultsContainerEl.value, {
        duration: 0.3,
        height: targetHeight === 1 ? 0 : targetHeight,
    });
}

/**
 * to animate the search results
 */
async function animateResults(): Promise<void> {
    await window.Vue.nextTick();
    const targetHeight = (searchResultsEl.value as HTMLDivElement).offsetHeight;
    animateHeight(targetHeight);
}

/**
 * results have been preset prior to search
 */
window.Vue.onMounted(async () => {
    if (props.results.length > 0) {
        await animateResults();
    }
});

/**
 * to slide up and down the search results
 */
window.Vue.watch(
    () => props.isActive,
    async () => {
        await animateResults();
    },
);

/**
 * to slide up and down the search results
 */
window.Vue.watch(
    () => props.results,
    async (newResults, oldResults) => {
        if (newResults.length === oldResults.length) {
            return;
        }
        await animateResults();
    },
);

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
 * To select the search result
 * @param result - the result selected
 */
function onSelect(result: Result): void {
    emit("select", result);
}

/**
 * To deselect the search result
 * @param result - the result deselected
 */
function onDeselect(result: Result): void {
    emit("deselect", result);
}

/**
 * To select all search results
 */
function onSelectAll(): void {
    emit("selectAll");
}

/**
 * To remove all search results
 */
function onRemoveAll(): void {
    emit("removeAll");
}

const activeIndex = window.Vue.ref(-1);

/**
 * to be able to move up and down through the selections
 * with the arrow keys
 */
function decrementActiveIndex(): void {
    if (activeIndex.value > 0) {
        activeIndex.value -= 1;
    }
}

/**
 * to be able to move up and down through the selections
 * with the arrow keys
 */
function incrementActiveIndex(): void {
    const maxIncrement = showSelectAll.value ? props.results.length : props.results.length - 1;
    if (showSelectAll.value && activeIndex.value < maxIncrement) {
        activeIndex.value += 1;
    }
}

/**
 * to be able to move down the search results
 */
onKeyStroke("ArrowDown", () => {
    incrementActiveIndex();
});

/**
 * to be able to move up the search results
 */
onKeyStroke("ArrowUp", () => {
    decrementActiveIndex();
});

/**
 * Reset the index whenever the search query changes
 */
window.Vue.watch(
    () => props.query,
    () => {
        activeIndex.value = 0;
    },
);
</script>
<template>
    <div ref="searchResultsContainerEl" class="search-results-container">
        <div ref="searchResultsEl" class="search-results">
            <div v-if="props.isSearching" class="search-status">Searching for results....</div>
            <div v-else-if="props.hasNoResults && props.selectedItems.length === 0" class="search-status">
                No results found for your current search.
            </div>
            <template v-else>
                <search-select-select-all
                    v-if="showSelectAll"
                    :results="props.results"
                    :selected-items="props.selectedItems"
                    :index="0"
                    :active-index="activeIndex"
                    @select-all="onSelectAll"
                    @remove-all="onRemoveAll"
                />
                <search-select-result
                    v-for="(result, index) in props.results"
                    :key="result.id"
                    :data-test="`search-select-result-${result.id}`"
                    :query="props.query"
                    :selected-items="props.selectedItems"
                    :result="result"
                    :is-single-select="props.isSingleSelect"
                    :index="showSelectAll ? index + 1 : index"
                    :active-index="activeIndex"
                    @select="onSelect"
                    @deselect="onDeselect"
                />
            </template>
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
    &:last-child {
        border-bottom: 1px solid var(--form-border-color);
    }
}

.search-status {
    background: var(--color-foreground-primary);
    border-left: 1px solid var(--form-border-color);
    border-right: 1px solid var(--form-border-color);
    padding: var(--spacing-s);
    font-size: 0.825rem;
    font-style: italic;
}
</style>
