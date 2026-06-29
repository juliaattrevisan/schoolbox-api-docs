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
    <div class="u-flex-col l-gap-m">
        <!-- using index as the key is typically not advised, however as the results will never change their order, it's fine in this instance -->
        <sp-results-result
            v-for="(result, index) in resultsStore.allResults"
            :key="index"
            :result="result"
            :index="index"
        />
        <sp-results-load-more />
    </div>
    <sp-badge-award />
</template>

<style scoped></style>
