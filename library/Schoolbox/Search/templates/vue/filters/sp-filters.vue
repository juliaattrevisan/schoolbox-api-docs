<script setup lang="ts">
/**
 * To customise the search results via filters
 */

import SpFilterSearchTerm from "./sp-filter-search-term.vue";
import SpFilterDateRange from "./sp-filter-date-range.vue";
import SpFilterCreator from "./sp-filter-creator.vue";
import SpFilterTypes from "./sp-filter-types.vue";
import { useStore } from "../store/searchPageStore";
import { useFiltersStore } from "./store/spFiltersStore";
import { useSearchStore } from "../store/spSearchStore";
import { useQueryStore } from "../store/spQueryStore";

const store = useStore();
const filtersStore = useFiltersStore();
const searchStore = useSearchStore();
const queryStore = useQueryStore();
const isOpen = window.Vue.ref(true); // whether the filters are shown

/**
 * Whenever the input is updated, update the query store
 * @param {string} value - The input value
 */
function onInput(value: string) {
    queryStore.query = value;
}

/**
 * To show/hide the filters when selected
 */
function onToggleSelected() {
    isOpen.value = !isOpen.value;
}

/**
 * To show/hide the filters container
 * @returns {string} - the height of the sub types container
 */
const containerHeight = window.Vue.computed(() => {
    return isOpen.value ? "1fr" : "0fr";
});

/**
 * To update the title of the button
 * @returns {string} - the title of the button
 */
const titleText = window.Vue.computed(() => {
    return isOpen.value ? "Hide Filters" : "Show Filters";
});

/**
 * clear all the selected filters
 */
function onClearAllSelected() {
    filtersStore.clear();
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
 * Track the column state, to ensure the fitlers are shown when the page is resizied to above single column
 */
window.Vue.watch(
    () => store.isSingleColumn,
    (newValue, oldValue) => {
        if (newValue !== oldValue && newValue === false) {
            isOpen.value = true;
        }
    }
);
</script>

<template>
    <form
        class="l-flex-col l-gap-s l-flex__item--0-shrink f-no-margins"
        role="search"
        aria-label="Sitewide"
        @submit.prevent="onSearch"
    >
        <div class="c-island l-pad-m">
            <sp-filter-search-term
                :initial-input="queryStore.query"
                :is-active-when-empty="true"
                @input="onInput"
                @key-enter="onSearch"
                @click="onSearch"
            />
        </div>
        <div class="c-island c-filters l-flex-col l-gap-m l-pad-m">
            <button
                v-if="store.isSingleColumn"
                :class="`c-filters__toggle-button ${isOpen ? 'icon-up-arrow' : 'icon-down-arrow'}`"
                @click.prevent="onToggleSelected"
            >
                {{ titleText }}
            </button>
            <div class="c-filters__container" :style="{ 'grid-template-rows': containerHeight }">
                <div class="c-filters__inner-container l-flex-col l-gap-m">
                    <sp-filter-date-range />
                    <sp-filter-creator />
                    <sp-filter-types />
                    <div class="l-flex-row l-flex--no-wrap l-gap-m">
                        <button class="button l-flex__item--1 icon-close" @click.prevent="onClearAllSelected">
                            Clear filters
                        </button>
                        <button class="button submit l-flex__item--1 icon-search" @click.prevent="onSearch">
                            Search
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</template>

<style lang="scss" scoped>
.c-island {
    background-color: var(--foreground-colour);
}
.c-filters {
    &__container {
        display: grid;
        transition: grid-template-rows 300ms;
    }

    &__inner-container {
        overflow: hidden;
    }

    &__toggle-button {
        background: none;
        color: var(--content-ui-foreground);
        font-weight: bold;
        padding: var(--spacing-xs) 0;
        text-align: left;
    }
}
</style>
