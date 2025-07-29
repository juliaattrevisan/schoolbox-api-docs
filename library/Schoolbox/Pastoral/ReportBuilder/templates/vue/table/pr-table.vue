<script setup lang="ts">
/**
 * Displays a pastoral report in a table format.
 */

import { useTemplateRef } from "vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { Tab, TablePayload, Config, Routes } from "../../js/types/pastoralReportTypes";
import { TabType } from "../../js/types/pastoralReportTypes";
import { useLoadPrTable } from "../composable/useLoadPrTable";
import { useLoadRecords } from "../composable/useLoadRecords";
import PrTableHeader from "./pr-table-header.vue";
import PrTableTotalRow from "./pr-table-total-row.vue";
import PrTableRow from "./pr-table-row.vue";
import PrTableFooter from "./pr-table-footer.vue";

const props = defineProps<{
    selectedTab: Tab;
    config: Config;
    routes: Routes;
    recordsAndPoints: string[];
    localePastoral: string;
}>();

// #region scrollable height

const scrollableEl = useTemplateRef<HTMLDivElement>("scrollableEl");
const maxScrollableHeight = window.Vue.ref<string>(`${window.innerHeight - 200}px`); // 200px chosen to give breathing room for header and metadata

/**
 * Set the max height to allow the thead to use sticky position
 * and to incrementally display large row lists on scroll
 */
function setScrollableHeight(): void {
    maxScrollableHeight.value = `${window.innerHeight - 300}px`;
}

// #endregion

// #region table load

const recordsOrPoints = defineModel<"records" | "points">("records-or-points", { default: "records" });
const queryParams = new URLSearchParams(); // to store the query parameters for the endpoint to update
const LIMIT = 20; // the current set limit for the number of records to fetch at once
const sortBy = window.Vue.ref<string>("user"); // the current sort by field - this should update when payload is loaded
const order = window.Vue.ref<"ASC" | "DESC">("ASC"); // the current sort order
const currentEndpoint = window.Vue.ref("");
const { isNewLoading, isSameLoading, payload, isEmpty, loadError } = useLoadPrTable(currentEndpoint);

/**
 * Reset the table load by clearing the cursor and reloading the data
 */
function resetTableLoad(): void {
    queryParams.set("selected", recordsOrPoints.value);
    queryParams.set("cursor", "");
    queryParams.set("limit", LIMIT.toString());
    queryParams.set("sortBy", sortBy.value);
    queryParams.set("order", order.value);
    currentEndpoint.value = `${props.selectedTab.endpoint}?${queryParams.toString()}`;
}

/**
 * Load the row data again when the recordsOrPoints changes
 */
window.Vue.watch(recordsOrPoints, () => {
    queryParams.set("selected", recordsOrPoints.value);
    currentEndpoint.value = `${props.selectedTab.endpoint}?${queryParams.toString()}`;
});

/**
 * Handle sort button click
 * @param headerKey - the key of the header that was clicked for sorting
 */
function onSort(headerKey: string): void {
    // If clicking the same header, toggle sort order
    if (sortBy.value === headerKey) {
        order.value = order.value === "ASC" ? "DESC" : "ASC";
    } else {
        // New header selected, set to ascending and update sortBy
        sortBy.value = headerKey;
        order.value = "ASC";
    }
    queryParams.set("sortBy", sortBy.value);
    queryParams.set("order", order.value);
    queryParams.set("cursor", ""); // Reset cursor on sort change
    currentEndpoint.value = `${props.selectedTab.endpoint}?${queryParams.toString()}`;
}

/**
 * Handle previous/next page button clicks
 * @param cursor - the cursor to use for the previous/next page
 */
function onPaginate(cursor: string): void {
    queryParams.set("cursor", cursor);
    currentEndpoint.value = `${props.selectedTab.endpoint}?${queryParams.toString()}`;
}

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

// #endregion

// #region table cell selected

const isCellExpanded = window.Vue.ref(false);
const recordsEndpoint = window.Vue.ref("");
const {
    isLoading: isLoadingRecords,
    payload: recordsPayload,
    loadError: recordsLoadError,
} = useLoadRecords(recordsEndpoint);

/**
 * Handle cell selection
 * @param key - the key of the cell that was selected
 */
function onCellSelect(key: string): void {
    console.warn("Cell selected:", key);
    isCellExpanded.value = true; // Open the modal to show related records
}

// #endregion

/**
 * Start setting the scrollable height on any window resize change
 * and reset the table load to ensure the correct endpoint is set
 */
window.Vue.onMounted(() => {
    setScrollableHeight();
    window.addEventListener("resize", () => {
        setScrollableHeight();
    });
    resetTableLoad();
});
</script>

<template>
    <div v-if="isNewLoading" class="c-island l-pad-m" data-test="pr-table-loading">
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
    <div v-else-if="isEmpty" class="empty-state-flex-centered">
        <i class="icon-pastoral"></i>
        <p>No {{ props.localePastoral }} records matched your report criteria.</p>
        <a :href="props.routes.getIndex"> Select Another Report </a>
    </div>
    <template v-else>
        <div
            ref="scrollableEl"
            class="table-container scrollable"
            :style="{ maxHeight: maxScrollableHeight }"
            data-test="pr-table"
        >
            <table class="pr-table">
                <pr-table-header
                    v-model:records-or-points="recordsOrPoints"
                    :headers="(payload as TablePayload).data.tableHeaders"
                    :config="props.config"
                    :records-and-points="props.recordsAndPoints"
                    :sort-by="sortBy"
                    :order="order"
                    @sort="onSort"
                />
                <!-- Skeleton loading state when switching between records/rows and sorting -->
                <tbody v-if="isSameLoading" class="skeleton">
                    <tr v-for="(row, index) in 5" :key="index">
                        <td colspan="100%"></td>
                    </tr>
                </tbody>
                <tbody v-else>
                    <!-- Total row -->
                    <pr-table-total-row
                        :headers="(payload as TablePayload).data.tableHeaders"
                        :row-data="(payload as TablePayload).data.totalRow"
                        :records-or-points="recordsOrPoints"
                        @cell-select="onCellSelect"
                    />
                    <!-- All other rows -->
                    <pr-table-row
                        v-for="(row, index) in (payload as TablePayload).data.rows"
                        :key="`row-${index}`"
                        :headers="(payload as TablePayload).data.tableHeaders"
                        :row-data="row"
                        :records-or-points="recordsOrPoints"
                    />
                </tbody>
            </table>
            <pr-table-footer
                :endpoint="currentEndpoint"
                :metadata="(payload as TablePayload).metadata"
                :limit="LIMIT"
                @previous="onPaginate"
                @next="onPaginate"
            />
        </div>
    </template>
    <basic-modal v-model:is-open="isCellExpanded">
        <p>Related cell records will be shown here.</p>
    </basic-modal>
</template>

<style scoped lang="scss">
.scrollable {
    overflow-y: auto;
}

.pr-table {
    width: 100%;
    /* fix the head to stay at the top when scrolling */
    thead {
        background: #ffffff; /* Ensure the header has a background to prevent transparency */
        position: sticky;
        top: 0;
        z-index: var(--sbx-depth-ui-sticky);
    }
}

.pr-table-metadata {
    background-color: #f9f9f9;
    border-top: 1px solid #e0e0e0;
    margin-top: 1rem;
    padding: 0.5rem;
    &__count {
        color: #666;
        font-size: 0.9rem;
    }
}
</style>
