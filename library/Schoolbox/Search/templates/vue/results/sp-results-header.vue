<script setup lang="ts">
/**
 * To indicate the status of the search, whether results are being shown
 * or there's no results to be found
 */

import { useResultsStore } from "./store/spResultsStore";

const resultsStore = useResultsStore();
</script>

<template>
    <h1 v-if="resultsStore.hasResults" class="header">
        Showing results for <strong>{{ resultsStore.activeQuery }}</strong>
    </h1>
    <h1 v-else-if="resultsStore.hasNoResults" class="header">
        No results for <strong>{{ resultsStore.activeQuery }}</strong>
    </h1>
</template>

<style scoped></style>
