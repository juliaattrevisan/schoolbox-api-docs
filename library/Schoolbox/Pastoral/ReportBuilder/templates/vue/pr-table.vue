<script setup lang="ts">
/**
 * Displays a pastoral report in a table format.
 */

import { payload } from "./tempData";
import type { PastoralReportTablePayload } from "../js/types/pastoralReportTypes";
import PrTableHeader from "./pr-table-header.vue";
import PrTableTotalRow from "./pr-table-total-row.vue";
import PrTableRow from "./pr-table-row.vue";

type Props = {
    recordsAndPoints: string[];
};
const props = defineProps<Props>();

const recordsOrPoints = defineModel<string>("records-or-points");

const payloadData = window.Vue.ref<PastoralReportTablePayload>(payload);

const handleSort = (key: string): void => {
    // TODO: Implement sorting logic
    // Sort data by the provided key: ${key}
    void key; // Suppress unused variable warning
};
</script>

<template>
    <table class="pr-table">
        <pr-table-header
            v-model:records-or-points="recordsOrPoints"
            :headers="payloadData.data.tableHeaders"
            :records-and-points="props.recordsAndPoints"
            @sort="handleSort"
        />
        <tbody>
            <!-- Total row -->
            <pr-table-total-row :headers="payloadData.data.tableHeaders" :row-data="payloadData.data.totalRow" />
            <!-- All other rows -->
            <pr-table-row
                v-for="(row, index) in payloadData.data.rows"
                :key="`row-${index}`"
                :headers="payloadData.data.tableHeaders"
                :row-data="row"
            />
        </tbody>
    </table>
    <!-- Table metadata -->
    <div class="pr-table-metadata">
        <div class="pr-table-metadata__count">
            Showing {{ payloadData.data.rows.length }} of {{ payloadData.metadata.count }} records
        </div>
    </div>
</template>

<style scoped lang="scss"></style>
