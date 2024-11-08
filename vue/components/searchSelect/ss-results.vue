<script setup lang="ts">
/**
 * The search results container
 */

import { toRefs, useTemplateRef } from "vue";
import { onKeyStroke, useScroll, useResizeObserver } from "@vueuse/core";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import SsResultsSelectAll from "./ss-results-select-all.vue";
import SsResultsItem from "./ss-results-item.vue";

interface Props {
    query: string; // the search query
    isActive: boolean; // whether the search is active
    isSearching: boolean; // to notify of searching
    isLoadingMore?: boolean; // whether more results are being loaded
    allowPartialMatches?: boolean; // whether to allow partial matches, so can match part of a word
    isSingleSelect: boolean; // whether only one result can be selected
    results: SearchSelectItem[]; // the results returned from the search
    hasNoResults: boolean; // whether there are no results
    hasError: boolean; // whether there's an error
    selected: SearchSelectItem[]; // the selected results
}
const props = defineProps<Props>();

const emit = defineEmits<{
    add: [items: SearchSelectItem[]];
    remove: [items: SearchSelectItem[]];
    atBottom: []; // scrolled to the bottom of the results container
}>();

/**
 * show select all option for adding / removing results only if there's
 * more than one search result and only a single result can't be selected
 * @returns - whether to show the select all option
 */
const showSelectAll = window.Vue.computed(() => {
    if (props.isSingleSelect) {
        return false;
    }
    if (!props.isActive) {
        // don't show select all if the search is not active (as the default results will be shown instead)
        return false;
    }
    if (props.results.length <= 1) {
        return false;
    }
    return true;
});

/**
 * To add results
 * @param items - the items selected
 */
function onAdd(items: SearchSelectItem[]): void {
    emit("add", items);
}

/**
 * To remove results
 * @param items - the items deselected
 */
function onRemove(items: SearchSelectItem[]): void {
    emit("remove", items);
}

const activeIndex = window.Vue.ref(-1); // to be able to use keyboard navigation to move through the search results

/**
 * Move down the search results
 */
onKeyStroke("ArrowDown", () => {
    const maxIncrement = showSelectAll.value ? props.results.length : props.results.length - 1;
    if (activeIndex.value < maxIncrement) {
        activeIndex.value += 1;
    }
});

/**
 * Move up the search results
 */
onKeyStroke("ArrowUp", () => {
    if (activeIndex.value > 0) {
        activeIndex.value -= 1;
    }
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

/**
 * Set the active index to the result currently hovered over
 * @param index - the index of the hovered result
 */
function onHover(index: number): void {
    activeIndex.value = index;
}

const resultsEl = useTemplateRef<HTMLDivElement | null>("resultsEl"); // to detect when the results container is scrolled to the bottom
const maxResultsHeight = 400; // the maximum height of the results container

const currentHeight = window.Vue.ref(0);
useResizeObserver(resultsEl, (entries) => {
    const entry = entries[0];
    currentHeight.value = entry.contentRect.height;
});

/**
 * To notify the parent component when the results container
 * is scrolled to the bottom, usually to load more results
 */
const { arrivedState } = useScroll(resultsEl);
const { bottom } = toRefs(arrivedState);
window.Vue.watch(bottom, (isAtBottom) => {
    if (isAtBottom && currentHeight.value === maxResultsHeight - 2) {
        // -2 to account for the border
        emit("atBottom");
    }
});

/**
 * Shift the results container scroll to the bottom so the loading of more results note is visible
 */
window.Vue.watch(
    () => props.isLoadingMore,
    async (newIsLoadingMore) => {
        if (newIsLoadingMore && resultsEl.value) {
            await window.Vue.nextTick();
            resultsEl.value.scrollTop = resultsEl.value.scrollHeight;
        }
    },
);
</script>
<template>
    <div ref="resultsEl" class="results">
        <div v-if="props.isSearching" class="results__status">Searching for results....</div>
        <div v-else-if="props.hasNoResults && props.selected.length === 0" class="results__status">
            No results found for your current search.
        </div>
        <fieldset v-else class="results__fieldset">
            <legend class="t-visually-hidden">Search results</legend>
            <ss-results-select-all
                v-if="showSelectAll"
                :results="props.results"
                :selected="props.selected"
                :index="0"
                :active-index="activeIndex"
                @add="onAdd"
                @remove="onRemove"
                @hover="onHover"
            />
            <ss-results-item
                v-for="(result, index) in props.results"
                :key="result.id"
                :query="props.query"
                :allow-partial-matches="props.allowPartialMatches"
                :selected="props.selected"
                :result="result"
                :is-single-select="props.isSingleSelect"
                :index="showSelectAll ? index + 1 : index"
                :active-index="activeIndex"
                @add="onAdd"
                @remove="onRemove"
                @hover="onHover"
            />
        </fieldset>
        <div v-if="props.hasError" class="results__status alert">
            Sorry, there was an unknown error retrieving your search results. Please try again and contact your support
            if the problem persists.
        </div>
        <div v-if="props.isLoadingMore" class="results__status">Loading more results....</div>
    </div>
</template>

<style lang="scss" scoped>
.results {
    border: 1px solid var(--form-border-color);
    max-height: 400px;
    overflow: auto;
    width: 100%;
    &__status {
        background: var(--color-foreground-primary);
        font-size: 0.825rem;
        font-style: italic;
        padding: var(--spacing-s);
    }
    &__fieldset {
        border: none;
        margin: 0;
        padding: 0;
    }
}
</style>
