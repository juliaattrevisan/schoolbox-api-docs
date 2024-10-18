<script setup lang="ts">
/**
 * The search results container
 */

import { onKeyStroke } from "@vueuse/core";
import type { SearchSelectItem } from "./types/searchSelectTypes";
import SsResultsListSelectAll from "./ss-results-list-select-all.vue";
import SsResultsListItem from "./ss-results-list-item.vue";

interface Props {
    query: string; // the search query
    isActive: boolean; // whether the search is active
    isSearching: boolean; // to notify of searching
    isSingleSelect: boolean; // whether only one result can be selected
    results: SearchSelectItem[]; // the results returned from the search
    hasNoResults: boolean; // whether there are no results
    selectedItems: SearchSelectItem[]; // the selected results
}
const props = defineProps<Props>();

const emit = defineEmits<{
    add: [results: SearchSelectItem[]];
    remove: [results: SearchSelectItem[]];
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
    if (props.results.length <= 1) {
        return false;
    }
    return true;
});

/**
 * To only show the bottom border if the search is active,
 * otherwise there's a double border when the search is inactive
 * @returns - the bottom border style
 */
const bottomBorder = window.Vue.computed(() => {
    return props.isActive ? "1px solid var(--form-border-color)" : "none";
});

/**
 * To add results
 * @param results - the results selected
 */
function onAdd(results: SearchSelectItem[]): void {
    emit("add", results);
}

/**
 * To remove results
 * @param results - the results deselected
 */
function onRemove(results: SearchSelectItem[]): void {
    emit("remove", results);
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
</script>
<template>
    <div class="results">
        <div v-if="props.isSearching" class="results__status">Searching for results....</div>
        <div v-else-if="props.hasNoResults && props.selectedItems.length === 0" class="esults__status">
            No results found for your current search.
        </div>
        <template v-else>
            <ss-results-list-select-all
                v-if="showSelectAll"
                :results="props.results"
                :selected-items="props.selectedItems"
                :index="0"
                :active-index="activeIndex"
                @add="onAdd"
                @remove="onRemove"
                @hover="onHover"
            />
            <ss-results-list-item
                v-for="(result, index) in props.results"
                :key="result.id"
                :query="props.query"
                :selected-items="props.selectedItems"
                :result="result"
                :is-single-select="props.isSingleSelect"
                :index="showSelectAll ? index + 1 : index"
                :active-index="activeIndex"
                @add="onAdd"
                @remove="onRemove"
                @hover="onHover"
            />
        </template>
    </div>
    <!-- .results -->
</template>

<style lang="scss" scoped>
.results {
    max-height: 400px;
    overflow: auto;
    width: 100%;
    &:last-child {
        border-bottom: v-bind(bottomBorder);
    }
    &__status {
        background: var(--color-foreground-primary);
        border-left: 1px solid var(--form-border-color);
        border-right: 1px solid var(--form-border-color);
        font-size: 0.825rem;
        font-style: italic;
        padding: var(--spacing-s);
    }
}
</style>
