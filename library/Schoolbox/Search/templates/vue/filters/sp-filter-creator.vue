<script setup lang="ts">
/**
 * The creator filter input, to show results linked to a specific user
 * currently uses the exisitng jquery selectize plugin
 */

import type { Result } from "@schoolboxVue/components/searchSelect/types/Result";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { Creator, useFilterCreatorStore } from "./store/spFilterCreatorStore";

const filterCreatorStore = useFilterCreatorStore();

/**
 * We only allow one creator to be selected at a time,
 * so only select a result if there isn't one already selected
 * or unselect the current result if it's already selected
 * @param {Result} result - the result selected
 */
function onSelected(result: Result) {
    if (!filterCreatorStore.isActive) {
        filterCreatorStore.creator.push(result);
        return;
    }
    if (result.id === filterCreatorStore.creator[0].id) {
        filterCreatorStore.clear();
    }
}

/**
 * Remove the result, as we only allow one creator to be selected at a time
 * we can simply just clear the store
 */
function onRemoved() {
    filterCreatorStore.clear();
}
</script>
<section></section>
<template>
    <div class="l-flex-col">
        <label for="creator">Creator</label>
        <search-select
            endpoint="/ajax/user.php?groupID=0&query="
            :selected="filterCreatorStore.creator"
            :is-single-select="true"
            @select="onSelected"
            @remove="onRemoved"
        />
    </div>
</template>

<style scoped></style>
