<script setup lang="ts">
/**
 * To display the list of returned results
 */

import { useResultsStore } from "../store/spResultsStore";
import SpResultsListLoadMore from "./sp-results-list-load-more.vue";

const resultsStore = useResultsStore();
</script>

<template>
    <div class="results" data-test-sp-results-list>
        <div v-for="result in resultsStore.results" :key="result.id" class="result">
            <h2>{{ result.title }}</h2>
            <p class="meta">{{ result.description }}</p>
            <template v-if="result._subResults">
                <div v-for="subResult in result._subResults.data" :key="subResult.id" class="sub-result">
                    <h3>{{ subResult.title }}</h3>
                    <p class="meta">{{ subResult.description }}</p>
                </div>
            </template>
        </div>
        <sp-results-list-load-more />
    </div>
</template>

<style scoped>
.results {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.result {
    background: white;
    padding: 1rem;
}

.sub-result {
    background: #f5f5f5;
    padding: 1rem;
    margin-left: 1rem;
}
</style>
