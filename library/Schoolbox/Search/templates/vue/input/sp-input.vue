<script setup lang="ts">
/**
 * The search input to start searching for results
 */

import { useSearchStore } from "../store/spSearchStore";

const searchStore = useSearchStore();

/**
 * Toggle the search
 */
async function onSearch() {
    await searchStore.search();
}
</script>

<template>
    <form @submit.prevent="onSearch">
        <input v-model="searchStore.query" type="text" />
        <button type="submit">Search</button>
    </form>
</template>

<style scoped></style>
