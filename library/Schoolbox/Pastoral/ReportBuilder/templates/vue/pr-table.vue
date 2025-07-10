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
const sortBy = window.Vue.ref<string>("user"); // the current sort by field - this should update when payload is loaded
const sortOrder = window.Vue.ref<"asc" | "desc">("asc"); // the current sort order

const isLoading = window.Vue.ref<boolean>(false);
const loadError = window.Vue.ref<string | null>(null);
// const { isLoading, payload, isEmpty, loadError } = useLoadPrTable(currentEndpoint);

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
 * @param headerKey - the key of the header that was clicked for sorting
 */
function onSortSelected(headerKey: string): void {
    // If clicking the same header, toggle sort order
    if (sortBy.value === headerKey) {
        sortOrder.value = sortOrder.value === "asc" ? "desc" : "asc";
    } else {
        // New header selected, set to ascending and update sortBy
        sortBy.value = headerKey;
        sortOrder.value = "asc";
    }
    // TODO: Implement actual sorting logic here
    // This could trigger a new data load with sorting parameters
    // or sort the existing data in payloadData
}
</script>

<template>
    <div v-if="isLoading" class="c-island l-pad-m" data-test="pr-table-loading">
        <div class="loader"></div>
    </div>
    <div v-else-if="loadError" class="c-island l-pad-m">
        <div class="alert-box alert f-no-margins">
            Sorry, there was an error during report retrieval. Please
            <a href="" title="refresh the page"> refresh the page </a>
            to try again and contact your support if the problem persists.
            <template v-if="loadError && loadError.length > 0"
                ><em>Further support info: {{ loadError }}</em></template
            >
        </div>
    </div>
    <template v-else>
        <table class="pr-table">
            <pr-table-header
                v-model:records-or-points="recordsOrPoints"
                :headers="payloadData.data.tableHeaders"
                :records-and-points="props.recordsAndPoints"
                :sort-by="sortBy"
                :sort-order="sortOrder"
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
</template>

<style scoped lang="scss"></style>
