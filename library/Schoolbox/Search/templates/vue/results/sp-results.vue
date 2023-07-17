<script setup lang="ts">
/**
 * To display the current search results
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import SpResultsHeader from "./sp-results-header.vue";
import SpResultsPrompt from "./sp-results-prompt.vue";
import SpResultsError from "./sp-results-error.vue";
import SpResultsNone from "./sp-results-none.vue";
import SpResultsList from "./sp-results-list.vue";
import { useSearchStore } from "../store/spSearchStore";
import { useResultsStore } from "./store/spResultsStore";

const searchStore = useSearchStore();
const resultsStore = useResultsStore();
</script>

<template>
    <sp-results-header />
    <sp-results-prompt v-if="searchStore.hasNotSearched" />
    <loading-table v-else-if="searchStore.isSearching" />
    <sp-results-error v-else-if="searchStore.hasLoadError" />
    <sp-results-none v-else-if="resultsStore.hasNoResults" />
    <sp-results-list v-else-if="resultsStore.hasResults" />
</template>

<style scoped></style>
