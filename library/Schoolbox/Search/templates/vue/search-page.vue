<script setup lang="ts">
/**
 * To search Schoolobx
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import SpResultsPrompt from "./results/sp-results-prompt.vue";
import SpResultsError from "./results/sp-results-error.vue";
import SpResultsNone from "./results/sp-results-none.vue";
import SpResults from "./results/sp-results.vue";
import SpFilters from "./filters/sp-filters.vue";
import { useStore } from "./store/searchPageStore";
import type { FilterType } from "./filters/store/spFilterTypesStore";
import { useSearchStore } from "./store/spSearchStore";
import { useResultsStore } from "./results/store/spResultsStore";
import { SEARCH_PARAM } from "./store/spQueryStore";

interface Props {
    endpoint: string; // the search api endpoint
    filterTypes: FilterType[]; // the filter types available, will vary depending on SB plan
}
const props = defineProps<Props>();

const store = useStore();
store.init(props.endpoint, props.filterTypes); // initialise the store

const searchStore = useSearchStore();
const resultsStore = useResultsStore();
const searchPageEl = window.Vue.ref<HTMLDivElement>();

/**
 * Initialise the resize observer to check the width of the search page container
 * This is done so the search page moves to single column at a certain breakpoint
 */
function initResizeObserver() {
    const resizeObserver = new ResizeObserver(() => {
        store.containerWidthChange((searchPageEl.value as HTMLDivElement).getBoundingClientRect().width);
    });
    resizeObserver.observe(searchPageEl.value as HTMLDivElement);
}

/**
 * start a search straight away if a keyword has been passed through
 */
window.Vue.onMounted(async () => {
    const searchParams = new URLSearchParams(document.location.search);
    initResizeObserver();
    if (searchParams.has(SEARCH_PARAM)) {
        await searchStore.search();
    }
});
</script>

<template>
    <div ref="searchPageEl" :class="`c-search-page ${store.isSingleColumn ? 'c-search-page--single-column' : ''}`">
        <sp-filters class="c-search-page__filters" />
        <div class="c-search-page__results l-flex-col l-gap-m">
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
    grid-template-columns: 400px 1fr;
    grid-template-rows: 1fr auto;
    &__filters {
        grid-area: filters;
    }
    &__results {
        grid-area: results;
    }
    // is applied when the search page is in single column mode
    &--single-column {
        gap: var(--spacing-m);
        grid-template-areas:
            "filters"
            "results";
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr;
    }
}
</style>
