<script setup lang="ts">
/**
 * To display the list of returned results
 */

import { useResultsStore } from "./store/spResultsStore";
import SpResultsListResult from "./sp-results-list-result.vue";
import SpResultsListLoadMore from "./sp-results-list-load-more.vue";

const resultsStore = useResultsStore();
</script>

<template>
    <div class="l-flex-col l-gap-s" data-test-sp-results-list>
        <sp-results-list-result
            v-for="(result, index) in resultsStore.results"
            :key="result.id"
            :result="result"
            :index="index"
        />
        <sp-results-list-load-more />
    </div>
</template>

<style scoped></style>
