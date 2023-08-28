<script setup lang="ts">
/**
 * The creator filter input, to show results linked to a specific user
 * currently uses the exisitng jquery selectize plugin
 */

import type { Result } from "@schoolboxVue/components/searchSelect/types/Result";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { Creator, useFilterCreatorStore } from "./store/spFilterCreatorStore";

// const options = window.Vue.ref([]);s
const selected = window.Vue.ref<Result[]>([]);

const filterCreatorStore = useFilterCreatorStore();

/**
 * We only allow one creator to be selected at a time,
 * so only select a result if there isn't one already selected
 * or unselect the current result if it's already selected
 * @param {Result} result - the result selected
 */
function onSelected(result: Result) {
    if (!filterCreatorStore.isSelected) {
        filterCreatorStore.creator = result as Creator;
        selected.value.push(result);
        return;
    }
    if (result.id === filterCreatorStore.creator.id) {
        filterCreatorStore.clear();
        selected.value = [];
    }
}
</script>
<section></section>
<template>
    <div class="l-flex-col">
        <label for="creator">Creator</label>
        <search-select
            endpoint="/ajax/user.php?groupID=0&query="
            :selected="selected"
            :is-single-select="true"
            @selected="onSelected"
        />
    </div>
</template>

<style scoped></style>
