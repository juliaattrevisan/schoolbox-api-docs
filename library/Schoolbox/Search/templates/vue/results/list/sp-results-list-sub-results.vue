<script setup lang="ts">
/**
 * To display linked sub results within a result
 */

import { Results } from "../../../js/types/Results";
// import { SpResultsListSubResults } from "./sp-results-list-sub-results.vue";

interface Props {
    subResults: Results; // the result to display
}
const props = defineProps<Props>();
</script>

<template>
    <div v-for="subResult in props.subResults.data" :key="subResult.id" class="sub-result">
        <h3>{{ subResult.title }}</h3>
        <p class="meta">{{ subResult.description }}</p>
    </div>
</template>

<style scoped></style>
