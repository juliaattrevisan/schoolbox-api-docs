<script setup lang="ts">
/**
 * The search input to start searching for results
 */

import { useResultsStore } from "../results/store/spResultsStore";

/**
 * Toggle the search
 */
async function onSearch() {
    await useResultsStore().search();
}
</script>

<template>
    <form @submit.prevent="onSearch">
        <input type="text" />
        <button type="submit">Search</button>
    </form>
</template>

<style scoped></style>
