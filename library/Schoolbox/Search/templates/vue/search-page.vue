<script setup lang="ts">
/**
 * To search Schoolobx
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import SpInput from "./input/sp-input.vue";
import SpResultsHeader from "./results/sp-results-header.vue";
import SpResultsPrompt from "./results/sp-results-prompt.vue";
import SpResultsError from "./results/sp-results-error.vue";
import SpResultsNone from "./results/sp-results-none.vue";
import SpResults from "./results/sp-results.vue";
import SpFilters from "./filters/sp-filters.vue";
import { useStore } from "./store/searchPageStore";
import type { FilterType } from "./filters/store/spFilterTypesStore";
import { useSearchStore } from "./store/spSearchStore";
import { useResultsStore } from "./results/store/spResultsStore";

interface Props {
    endpoint: string; // the search api endpoint
    filterTypes: FilterType[]; // the filter types available, will vary depending on SB plan
}
const props = defineProps<Props>();

// initialise the store
useStore().init(props.endpoint, props.filterTypes);

const searchStore = useSearchStore();
const resultsStore = useResultsStore();
</script>

<template>
    <div class="c-search-page">
        <!-- <sp-input class="c-search-page__input" /> -->
        <sp-filters class="c-search-page__filters" />
        <div class="c-search-page__results l-flex-col l-gap-m">
            <sp-results-header />
            <sp-results-prompt v-if="searchStore.hasNotSearched" />
            <loading-table v-else-if="searchStore.isSearching" />
            <sp-results-error v-else-if="searchStore.hasLoadError" />
            <sp-results-none v-else-if="resultsStore.hasNoResults" />
            <sp-results v-else-if="resultsStore.hasResults" />
        </div>
    </div>
</template>

<style lang="scss" scoped>
.c-search-page {
    display: grid;
    gap: var(--spacing-xl);
    grid-template-areas: "filters results";
    grid-template-columns: 500px 1fr;
    grid-template-rows: 1fr auto;
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
