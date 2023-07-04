<script setup lang="ts">
/**
 * To display an error if the search fails
 */

import { useResultsStore } from "../results/store/spResultsStore";

const resultsStore = useResultsStore();

/**
 * Try to search again
 */
async function onTryAgainSelected() {
    await resultsStore.search();
}
</script>

<template>
    <div class="alert-box alert">
        Sorry, there was an error retrieving your search results. Please
        <button title="try to search again" @click="onTryAgainSelected">try again</button> and contact your support if
        the problem persists.
    </div>
</template>

<style scoped></style>
