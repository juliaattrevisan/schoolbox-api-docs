<script setup lang="ts">
/**
 * The search input to start searching for results
 */

import { useResultsStore } from "../results/store/spResultsStore";

const resultsStore = useResultsStore();

/**
 * Toggle the search
 */
async function onSearch() {
    await resultsStore.search();
}
</script>

<template>
    <form @submit.prevent="onSearch">
        <input v-model="resultsStore.query" type="text" />
        <button type="submit">Search</button>
    </form>
</template>

<style scoped></style>
