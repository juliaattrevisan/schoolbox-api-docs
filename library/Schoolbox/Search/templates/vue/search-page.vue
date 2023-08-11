<script setup lang="ts">
/**
 * To search Schoolobx
 */

import SpInput from "./input/sp-input.vue";
import SpResults from "./results/sp-results.vue";
import SpFilters from "./filters/sp-filters.vue";
import { useStore } from "./store/searchPageStore";
import type { FilterType } from "./filters/store/spFilterTypesStore";

interface Props {
    endpoint: string; // the search api endpoint
    filterTypes: FilterType[]; // the filter types available, will vary depending on SB plan
}
const props = defineProps<Props>();

// initialise the store
useStore().init(props.endpoint, props.filterTypes);
</script>

<template>
    <div class="l-flex-col u-gap--xl">
        <div>
            <sp-input />
        </div>
        <div class="l-flex-row u-gap--xl">
            <sp-results />
            <sp-filters />
        </div>
    </div>
</template>

<style scoped></style>
