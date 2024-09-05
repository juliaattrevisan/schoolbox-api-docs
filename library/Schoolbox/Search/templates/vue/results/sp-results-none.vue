<script setup lang="ts">
/**
 * The notify that no results have been found
 */

import { useSearchStore } from "../store/spSearchStore";
import { useFiltersStore } from "../filters/store/spFiltersStore";

const searchStore = useSearchStore();
const filtersStore = useFiltersStore();

/**
 * Search again with all the filters cleared
 */
async function onNoFiltersSelected(): Promise<void> {
    await searchStore.clearFiltersAndSearch();
}
</script>

<template>
    <div class="empty-state-flex-centered" data-test="sp-results-none">
        <i class="icon-search"></i>
        <p>
            Sorry, but your search did not return any results.
            <template v-if="filtersStore.isActive">
                You can try again with
                <button title="Search with no filters active" @click="onNoFiltersSelected">no filters</button> otherwise
                modify your search term.</template
            >
        </p>
    </div>
</template>

<style scoped></style>
