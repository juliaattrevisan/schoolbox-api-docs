<script setup lang="ts">
/**
 * To search Schoolbox
 */

import LoadingCards from "@schoolboxVue/components/loading/loading-cards.vue";
import BadgeModal from "@schoolboxBadge/vue/badge-modal.vue";
import SpResultsPrompt from "./results/sp-results-prompt.vue";
import SpResultsError from "./results/sp-results-error.vue";
import SpResultsNone from "./results/sp-results-none.vue";
import SpResults from "./results/sp-results.vue";
import SpFilters from "./filters/sp-filters.vue";
import { useStore } from "./store/searchPageStore";
import type { FilterType } from "./filters/store/spFilterTypesStore";
import type { FilterDateRange } from "./filters/store/spFilterDateRangeStore";
import { useSearchStore } from "./store/spSearchStore";
import { useResultsStore } from "./results/store/spResultsStore";
import { useBadgeStore } from "./results/store/spBadgeStore";
import { Creator } from "./filters/store/spFilterCreatorsStore";
import { SEARCH_PARAM } from "./store/spQueryStore";

interface Props {
    endpoint: string; // The search api endpoint.
    filterTypesAvailable: FilterType[]; // The filter types available, will vary depending on SB plan.
    filterTypesSelected: string[]; // The selected filter types.
    filterDateRange: FilterDateRange; // The default date range for the date filter.
    filterCreator: object | null; // The selected user - object {id: number, name: string}
}
const props = defineProps<Props>();

const store = useStore();
store.init(
    props.endpoint,
    props.filterDateRange,
    props.filterCreator as Creator,
    props.filterTypesAvailable,
    props.filterTypesSelected,
); // initialise the store

const searchStore = useSearchStore();
const resultsStore = useResultsStore();
const badgeStore = useBadgeStore();
const searchPageEl = window.Vue.ref<HTMLDivElement>();

/**
 * Initialise the resize observer to check the width of the search page container
 * This is done so the search page moves to single column at a certain breakpoint
 */
function initResizeObserver(): void {
    const resizeObserver = new ResizeObserver(() => {
        store.containerWidthChange((searchPageEl.value as HTMLDivElement).getBoundingClientRect().width);
    });
    resizeObserver.observe(searchPageEl.value as HTMLDivElement);
}

/**
 * When the back button is pressed, reload the page if there's a search query
 */
window.onpopstate = (): void => {
    const searchParams = new URLSearchParams(document.location.search);
    if (searchParams.has(SEARCH_PARAM)) {
        document.location.reload();
    }
};

/**
 * start a search straight away if a keyword has been passed through
 */
window.Vue.onMounted(async () => {
    const searchParams = new URLSearchParams(document.location.search);
    initResizeObserver();
    if (searchParams.has(SEARCH_PARAM)) {
        await searchStore.search(false);
    }
});

/**
 * the badge modal is closed, so we should set the boolean to
 * false so we can open it again
 */
function onBadgeModalClosed(): void {
    badgeStore.isDetailModalOpen = false;
}
</script>

<template>
    <div
        ref="searchPageEl"
        :class="`search-page l-grid l-gap-xl ${store.isSingleColumn ? 'search-page--single-column' : ''}`"
    >
        <sp-filters class="c-search-page__filters" />
        <div class="c-search-page__results l-flex-col l-gap-m">
            <sp-results-prompt v-if="searchStore.hasNotSearched" />
            <loading-cards v-else-if="searchStore.isSearching" />
            <sp-results-error v-else-if="searchStore.hasLoadError" />
            <sp-results-none v-else-if="resultsStore.hasNoResults" />
            <sp-results v-else-if="resultsStore.hasResults" />
        </div>
    </div>
    <badge-modal :is-open="badgeStore.isDetailModalOpen" :badge="badgeStore.detailBadge" @closed="onBadgeModalClosed" />
</template>

<style lang="scss" scoped>
.search-page {
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
