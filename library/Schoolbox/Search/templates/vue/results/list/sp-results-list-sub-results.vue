<script setup lang="ts">
/**
 * To display all linked sub results within a result
 */

import { Results } from "../../../js/types/Results";
import SpResultsListSubResult from "./sp-results-list-sub-result.vue";

interface Props {
    subResults: Results; // the result to display
}
const props = defineProps<Props>();
</script>

<template>
    <sp-results-list-sub-result
        v-for="subResult in props.subResults.data"
        :key="subResult.id"
        :sub-result="subResult"
    />
</template>

<style scoped></style>
