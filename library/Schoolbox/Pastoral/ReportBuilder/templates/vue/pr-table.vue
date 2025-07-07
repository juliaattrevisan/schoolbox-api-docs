<script setup lang="ts">
/**
 * Displays a pastoral report in a table format.
 */

import { payload } from "./tempData";
import type {
    PastoralReportTablePayload,
    ColumnTotalRecordsAndPoints,
    ColumnUser,
    ColumnRecordsAndPoints,
} from "../js/types/pastoralReportTypes";
import PrTableHeader from "./pr-table-header.vue";
import PrTableRow from "./pr-table-row.vue";

const data = window.Vue.ref<PastoralReportTablePayload>(payload);

const handleSort = (key: string): void => {
    // TODO: Implement sorting logic
    // Sort data by the provided key: ${key}
    void key; // Suppress unused variable warning
};

// Helper to create total row data
const createTotalRowData = (): (ColumnUser | ColumnRecordsAndPoints)[] => {
    return data.value.data.totalRows.map(
        (total: ColumnTotalRecordsAndPoints | null, index: number) =>
            total || ({ fullName: index === 0 ? "Total" : "" } as ColumnUser),
    );
};
</script>

<template>
    <table class="pr-table">
        <pr-table-header :headers="data.data.tableHeaders" @sort="handleSort" />
        <tbody>
            <!-- Total row if totals are provided -->
            <!-- <pr-table-row
                v-if="data.data.totalRows.some((total: ColumnTotalRecordsAndPoints | null) => total !== null)"
                :headers="data.data.tableHeaders"
                :row-data="createTotalRowData()"
                :is-total="true"
            /> -->
            <!-- Data rows -->
            <pr-table-row
                v-for="(row, index) in data.data.rows"
                :key="`row-${index}`"
                :headers="data.data.tableHeaders"
                :row-data="row"
            />
        </tbody>
    </table>
    <!-- Table metadata -->
    <div class="pr-table-metadata">
        <div class="pr-table-metadata__count">
            Showing {{ data.data.rows.length }} of {{ data.metadata.count }} records
        </div>
    </div>
</template>

<style scoped lang="scss"></style>
