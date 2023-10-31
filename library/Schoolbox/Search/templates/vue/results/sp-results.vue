<script setup lang="ts">
/**
 * To display the list of returned results
 */

import { useResultsStore } from "./store/spResultsStore";
import SpResultsResult from "./sp-results-result.vue";
import SpResultsLoadMore from "./sp-results-load-more.vue";
import SpBadgeAward from "./sp-badge-award.vue";

const resultsStore = useResultsStore();
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <sp-results-result
            v-for="(result, index) in resultsStore.allResults"
            :key="result.id"
            :result="result"
            :index="index"
        />
        <sp-results-load-more />
    </div>
    <sp-badge-award />
</template>

<style scoped></style>
