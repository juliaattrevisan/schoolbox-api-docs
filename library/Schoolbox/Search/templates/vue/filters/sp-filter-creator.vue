<script setup lang="ts">
/**
 * The creator filter input, to show results linked to a specific user
 */

import type { SearchSelectItem } from "@schoolboxVue/components/searchSelect/types/searchSelectTypes";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { Creator } from "./store/spFilterCreatorsStore";
import { useFilterCreatorsStore } from "./store/spFilterCreatorsStore";

const filterCreatorsStore = useFilterCreatorsStore();

const currentUser: Creator = {
    id: window.schoolboxUser.id,
    name: window.schoolboxUser.name,
};

/**
 * We only allow one creator to be selected at a time,
 * so only select a result if there isn't one already selected
 * or unselect the current result if it's already selected
 * @param results - the results selected
 */
function onAdded(results: SearchSelectItem[]): void {
    const result = results[0];
    if (!filterCreatorsStore.isActive) {
        filterCreatorsStore.creator.push(result as Creator);
        return;
    }
    if (result.id === filterCreatorsStore.creator[0].id) {
        filterCreatorsStore.clear();
    }
}

/**
 * Remove the result, as we only allow one creator to be selected at a time
 * we can simply just clear the store
 */
function onRemoved(): void {
    filterCreatorsStore.clear();
}
</script>
<section></section>
<template>
    <div class="l-flex-col">
        <search-select
            label="Creator"
            label-id="creator"
            endpoint="/ajax/user.php?groupID=0&query="
            :selected-items="filterCreatorsStore.creator"
            :is-single-select="true"
            :default-results="[currentUser as Result]"
            placeholder="Search for users..."
            @add="onAdded"
            @remove="onRemoved"
        />
    </div>
</template>

<style scoped></style>
