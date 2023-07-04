<script setup lang="ts">
/**
 * To display the current search results
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import SpResultsHeader from "./sp-results-header.vue";
import SpResultsPrompt from "./sp-results-prompt.vue";
import SpResultsNone from "./sp-results-none.vue";
import { useResultsStore } from "./store/spResultsStore";

const resultsStore = useResultsStore();
</script>

<template>
    <sp-results-header />
    <sp-results-prompt v-if="resultsStore.hasNotSearched" />
    <loading-table v-else-if="resultsStore.isLoading" />
    <div v-else-if="resultsStore.hasLoadError">Load error</div>
    <sp-results-none v-else-if="resultsStore.hasNoResults" />
    <div v-else>Show results</div>
</template>

<style scoped></style>
