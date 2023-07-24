<script setup lang="ts">
/**
 * Display a single search result
 */

import { Result } from "../../../js/types/Result";
import { SpResultsListSubResults } from "./sp-results-list-sub-results.vue";

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();
</script>

<template>
    <div class="result">
        <h2>{{ props.result.title }}</h2>
        <p class="meta">{{ result.meta }}</p>
        <sp-results-list-sub-results v-if="props.result._subResults" :sub-results="props.result._subResults" />
    </div>
</template>

<style scoped>
.result {
    background: white;
    padding: 1rem;
}
</style>
