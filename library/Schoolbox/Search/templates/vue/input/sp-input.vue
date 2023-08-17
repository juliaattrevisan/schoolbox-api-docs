<script setup lang="ts">
/**
 * The search input to submit and start searching for results
 */

import SearchInput from "@schoolboxVue/components/search/search-input.vue";
import { useQueryStore } from "../store/spQueryStore";
import { useSearchStore } from "../store/spSearchStore";

const queryStore = useQueryStore();
const searchStore = useSearchStore();

/**
 * Whenever the input is updated, update the query store
 * @param {string} value - The input value
 */
function onInput(value: string) {
    queryStore.query = value;
}

/**
 * Start a search
 */
async function onSearch() {
    if (searchStore.isSearching) {
        return;
    }
    await searchStore.search();
}
</script>

<template>
    <form @submit.prevent="onSearch">
        <search-input
            :is-active="true"
            :is-active-when-empty="true"
            @input="onInput"
            @key-enter="onSearch"
            @click="onSearch"
        />
        <!-- <input v-model="queryStore.query" type="text" /> -->
        <!-- <button type="submit">Search</button> -->
    </form>
</template>

<style scoped></style>
