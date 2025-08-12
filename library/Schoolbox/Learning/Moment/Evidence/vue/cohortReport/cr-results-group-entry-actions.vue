<script lang="ts" setup>
import { useCohortReportStore } from "./store/cohortReportStore";
import type { CohortReportEntry } from "./store/cohortReportPayload";

interface Props {
    entry: CohortReportEntry;
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();
</script>

<template>
    <li><a class="icon-approve"> Approve </a></li>
    <li><a class="icon-surveys"> Request Update </a></li>
    <li><a class="icon-modify"> Modify </a></li>
    <li><a class="icon-delete"> Delete </a></li>
</template>

<style scoped></style>
