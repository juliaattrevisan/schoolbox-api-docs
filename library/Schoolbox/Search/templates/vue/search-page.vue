<script setup lang="ts">
/**
 * To search Schoolobx
 */

import SpInput from "./input/sp-input.vue";
import SpResults from "./results/sp-results.vue";
import SpFilters from "./filters/sp-filters.vue";
import { useStore } from "./store/searchPageStore";

interface Props {
    endpoint: string; // the search api endpoint
}
const props = defineProps<Props>();

// initialise the store
useStore().init(props.endpoint);
</script>

<template>
    <div class="row">
        <div class="small-12 columns">
            <sp-input />
        </div>
    </div>
    <div class="row">
        <div class="small-12 large-9 island">
            <sp-results />
        </div>
        <div class="small-12 large-3 island">
            <sp-filters />
        </div>
    </div>
</template>

<style scoped></style>
