<script setup lang="ts">
/**
 * To display the list of returned results
 */

import { useResultsStore } from "../store/spResultsStore";
import SpResultsListResult from "./sp-results-list-result.vue";
import SpResultsListLoadMore from "./sp-results-list-load-more.vue";

const resultsStore = useResultsStore();
</script>

<template>
    <div class="results" data-test-sp-results-list>
        <sp-results-list-result v-for="result in resultsStore.results" :key="result.id" :result="result" />
        <sp-results-list-load-more />
    </div>
</template>

<style scoped>
.results {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.sub-result {
    background: #f5f5f5;
    padding: 1rem;
    margin-left: 1rem;
}
</style>
