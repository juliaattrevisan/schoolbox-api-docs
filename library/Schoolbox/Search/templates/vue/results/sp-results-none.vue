<script setup lang="ts">
/**
 * The notify that no results have been found
 */

import { useSearchStore } from "../store/spSearchStore";
import { useFiltersStore } from "../filters/store/spFiltersStore";

const searchStore = useSearchStore();
const fitersStore = useFiltersStore();

/**
 * Search again with all the filters cleared
 */
async function onNoFiltersSelected() {
    await searchStore.clearFiltersAndSearch();
}
</script>

<template>
    <div class="empty-state-flex-centered" data-test="sp-results-none">
        <i class="icon-search"></i>
        <p>
            Sorry, but your search did not return any results.
            <template v-if="fitersStore.isActive">
                You can try again with
                <button title="Search with no filters active" @click="onNoFiltersSelected">no filters</button> otherwise
                modify your search term.</template
            >
        </p>
        <p>Here are some tips for improving your search:</p>
        <ul>
            <li>
                Ensure your search is two or more letters long. If you need to search for something less than two
                letters long, "quote" it.
            </li>
            <li>You can also use "quotes" to search for an exact string, for example "dog and cat".</li>
        </ul>
    </div>
</template>

<style scoped></style>
