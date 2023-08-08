<script setup lang="ts">
/**
 * The search input to submit and start searching for results
 */

import { useQueryStore } from "../store/spQueryStore";
import { useSearchStore } from "../store/spSearchStore";

const queryStore = useQueryStore();
const searchStore = useSearchStore();

/**
 * Start a search
 */
async function onSearch() {
    if (!queryStore.isActive) {
        return;
    }
    if (searchStore.isSearching) {
        return;
    }
    await searchStore.search();
}
</script>

<template>
    <form @submit.prevent="onSearch">
        <input v-model="queryStore.query" type="text" />
        <button type="submit">Search</button>
    </form>
</template>

<style scoped></style>
