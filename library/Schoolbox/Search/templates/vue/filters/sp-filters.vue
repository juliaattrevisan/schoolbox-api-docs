<script setup lang="ts">
/**
 * To customise the search results via filters
 */

import SpFilterSearchTerm from "./sp-filter-search-term.vue";
import SpFilterDateRange from "./sp-filter-date-range.vue";
import SpFilterCreator from "./sp-filter-creator.vue";
import SpFilterTypes from "./sp-filter-types.vue";
import { useFiltersStore } from "./store/spFiltersStore";
import { useSearchStore } from "../store/spSearchStore";
import { useQueryStore } from "../store/spQueryStore";

const filtersStore = useFiltersStore();
const searchStore = useSearchStore();
const queryStore = useQueryStore();
const isOpen = window.Vue.ref(false); // whether the filters are shown

/**
 * Whenever the input is updated, update the query store
 * @param {string} value - The input value
 */
function onInput(value: string) {
    queryStore.query = value;
}

/**
 * apply the selected filters with the current search query
 */
async function onSearch() {
    if (searchStore.isSearching) {
        return;
    }
    await searchStore.search();
}

/**
 * clear all the selected filters
 */
function onClearAllSelected() {
    filtersStore.clear();
}
</script>

<template>
    <form
        class="l-flex-col l-gap-s l-flex__item--0-shrink f-no-margins"
        role="search"
        aria-label="Sitewide"
        @submit.prevent="onSearch"
    >
        <div class="c-filters">
            <sp-filter-search-term
                :initial-input="queryStore.query"
                :is-active-when-empty="true"
                @input="onInput"
                @key-enter="onSearch"
                @click="onSearch"
            />
        </div>
        <div class="c-filters l-flex-col l-gap-m">
            <button class="c-filters__toggle-button icon-up-arrow">Hide Filters</button>
            <sp-filter-date-range />
            <sp-filter-creator />
            <sp-filter-types />
            <div class="l-flex-col l-gap-s">
                <button class="full-width" @click.prevent="onClearAllSelected">Clear all filters</button>
                <button class="full-width submit" @click.prevent="onSearch">Apply filters and search</button>
            </div>
        </div>
    </form>
</template>

<style lang="scss" scoped>
.c-filters {
    background-color: var(--foreground-colour);
    padding: var(--spacing-m);

    &__toggle-button {
        background: none;
        color: var(--content-ui-foreground);
        font-weight: bold;
        padding: var(--spacing-xs) 0;
        text-align: left;
    }
}
</style>
