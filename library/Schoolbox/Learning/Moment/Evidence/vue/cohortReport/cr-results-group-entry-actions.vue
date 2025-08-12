<script lang="ts" setup>
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import { useCohortReportStore } from "./store/cohortReportStore";
import type { CohortReportEntry } from "./store/cohortReportPayload";

interface Props {
    entry: CohortReportEntry;
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();

/**
 * Approve this entry
 */
function onApprove(): void {
    alert("todo");
}

/**
 * Request an update for this entry
 */
function onRequestUpdate(): void {
    alert("todo");
}

/**
 * Show the delete modal for this entry
 */
function onDelete(): void {
    alert("todo");
}
</script>

<template>
    <li v-if="props.entry._links.publish">
        <a class="icon-approve" @click.prevent="onApprove">Approve</a>
    </li>
    <li v-if="props.entry._links.reject">
        <a class="icon-surveys" @click.prevent="onRequestUpdate">Request Update</a>
    </li>
    <li v-if="props.entry._links.modifyForm">
        <a class="icon-modify" :href="getReturnUrl(props.entry._links.modifyForm)">Modify</a>
    </li>
    <li v-if="props.entry._links.delete">
        <a class="icon-delete" @click.prevent="onDelete">Delete</a>
    </li>
</template>

<style scoped></style>
