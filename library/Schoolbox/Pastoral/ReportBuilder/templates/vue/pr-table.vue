<script setup lang="ts">
/**
 * Displays a pastoral report in a table format.
 */

import { payload as tempPayload } from "./tempData";
import type { Tab, TablePayload } from "../js/types/pastoralReportTypes";
import { TabType } from "../js/types/pastoralReportTypes";
import { useLoadPrTable } from "./composable/useLoadPrTable";
import PrTableHeader from "./pr-table-header.vue";
import PrTableTotalRow from "./pr-table-total-row.vue";
import PrTableRow from "./pr-table-row.vue";

const props = defineProps<{
    selectedTab: Tab;
    recordsAndPoints: string[];
}>();

const recordsOrPoints = defineModel<string>("records-or-points", { default: "Records" });

const payloadData = window.Vue.ref<TablePayload>(tempPayload);
const currentEndpoint = window.Vue.ref<string>(props.selectedTab.endpoint || ""); // the current endpoint to load data from

const { isLoading, payload, isEmpty, loadError } = useLoadPrTable(currentEndpoint);

/**
 * when the endpoint changes trigger a new load
 */
window.Vue.watch(
    () => props.selectedTab,
    (newTab) => {
        if (newTab.type === TabType.TABLE) {
            // endpoint change
        }
    },
);

/**
 * Handle sort button click
 */
function onSortSelected(): void {
    // Implement sorting logic here
    // tweak the endpoint to include sorting parameters so a new load is triggered
}
</script>

<template>
    <table class="pr-table">
        <pr-table-header
            v-model:records-or-points="recordsOrPoints"
            :headers="payloadData.data.tableHeaders"
            :records-and-points="props.recordsAndPoints"
            @sort="onSortSelected"
        />
        <tbody>
            <!-- Total row -->
            <pr-table-total-row
                :headers="payloadData.data.tableHeaders"
                :row-data="payloadData.data.totalRow"
                :records-or-points="recordsOrPoints"
            />
            <!-- All other rows -->
            <pr-table-row
                v-for="(row, index) in payloadData.data.rows"
                :key="`row-${index}`"
                :headers="payloadData.data.tableHeaders"
                :row-data="row"
                :records-or-points="recordsOrPoints"
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
