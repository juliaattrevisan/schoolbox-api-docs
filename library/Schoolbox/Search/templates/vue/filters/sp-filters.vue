<script setup lang="ts">
/**
 * To customise the search results via filters
 */

import SpFilterDateRange from "./sp-filter-date-range.vue";
import SpFilterCreator from "./sp-filter-creator.vue";
import SpFilterTypes from "./sp-filter-types.vue";
import { useFiltersStore } from "./store/spFiltersStore";
import { useSearchStore } from "../store/spSearchStore";

const filtersStore = useFiltersStore();
const searchStore = useSearchStore();

/**
 * clear all the selected filters
 */
function onClearAllSelected() {
    filtersStore.clear();
}

/**
 * apply the selected filters with the current search query
 */
async function onApplySelected() {
    if (searchStore.isSearching) {
        return;
    }
    await searchStore.search();
}
</script>

<template>
    <div class="c-filters l-flex-col l-gap-s l-flex__item--0-shrink f-no-margins">
        <h2 class="subheader">Filters</h2>
        <div class="c-filters__container">
            <div class="l-flex-col l-gap-m">
                <sp-filter-date-range />
                <!-- <sp-filter-creator /> -->
                <!-- <sp-filter-types /> -->
                <button class="full-width" @click.prevent="onClearAllSelected">Clear all filters</button>
                <button class="full-width submit" @click.prevent="onApplySelected">Apply filters and search</button>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
c-filters {
    width: 500px;
    &__container {
        background-color: var(--foreground-colour);
    }
}
</style>
