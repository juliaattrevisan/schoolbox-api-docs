<script setup>

/**
 * To list all the currently selected search results
 */

import { useSearchStore } from './store/mgSearchStore';
import MgSelectedSearchResult from './mg-selected-search-result.vue';


const searchStore = useSearchStore();


</script>

<template>
    <div v-if="searchStore.hasSelectedResults" class="selected-results-container">
        <mg-selected-search-result
            v-for="result in searchStore.selectedResults"
            :key="result.id"
            :result="result"
        />
    </div>
</template>

<style lang="scss" scoped>

.selected-results-container {
    border: 1px solid var(--form-border-color);
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: -1px; //to butt up against the search input
    min-height: 50px;
    padding: 0.5rem;
}

</style>