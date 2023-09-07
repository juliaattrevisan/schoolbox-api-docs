<script setup lang="ts">
/**
 * To search Schoolobx
 */

import SpInput from "./input/sp-input.vue";
import SpResults from "./results/sp-results.vue";
import SpFilters from "./filters/sp-filters.vue";
import { useStore } from "./store/searchPageStore";
import type { FilterType } from "./filters/store/spFilterTypesStore";

interface Props {
    endpoint: string; // the search api endpoint
    filterTypes: FilterType[]; // the filter types available, will vary depending on SB plan
}
const props = defineProps<Props>();

// initialise the store
useStore().init(props.endpoint, props.filterTypes);
</script>

<template>
    <div class="c-search-page">
        <sp-input class="c-search-page__input" />
        <sp-results class="c-search-page__results" />
        <sp-filters class="c-search-page__filters" />
    </div>
</template>

<style lang="scss" scoped>
.c-search-page {
    display: grid;
    gap: var(--spacing-xl);
    grid-template-areas:
        "input input"
        "results filters";
    grid-template-columns: 1fr 500px;
    grid-template-rows: auto 1fr;
    &__input {
        grid-area: input;
    }
    &__results {
        grid-area: results;
    }
    &__filters {
        grid-area: filters;
    }
}
</style>
