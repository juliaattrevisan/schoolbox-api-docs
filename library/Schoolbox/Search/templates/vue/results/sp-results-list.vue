<script setup lang="ts">
/**
 * To display the list of returned results
 */

import { useResultsStore } from "./store/spResultsStore";

const resultsStore = useResultsStore();
</script>

<template>
    <div data-test-sp-results-list>
        <div v-for="result in resultsStore.results" :key="result.id">
            {{ result.title }}
        </div>
    </div>
</template>

<style scoped></style>
