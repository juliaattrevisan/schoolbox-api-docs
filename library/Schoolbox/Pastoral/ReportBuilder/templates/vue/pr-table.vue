<script setup lang="ts">
/**
 * Displays a pastoral report in a table format.
 */

import { useTemplateRef } from "vue";
// import { payload as tempPayload } from "./tempData";
import type { Tab, TablePayload, Routes } from "../js/types/pastoralReportTypes";
import { TabType } from "../js/types/pastoralReportTypes";
import { useLoadPrTable } from "./composable/useLoadPrTable";
import PrTableHeader from "./pr-table-header.vue";
import PrTableTotalRow from "./pr-table-total-row.vue";
import PrTableRow from "./pr-table-row.vue";

const props = defineProps<{
    selectedTab: Tab;
    recordsAndPoints: string[];
    routes: Routes;
    localePastoral: string;
}>();

const recordsOrPoints = defineModel<string>("records-or-points", { default: "Records" });

const sortBy = window.Vue.ref<string>("user"); // the current sort by field - this should update when payload is loaded
const sortOrder = window.Vue.ref<"asc" | "desc">("asc"); // the current sort order
const currentEndpoint = window.Vue.ref("");
const { isLoading, payload, isEmpty, loadError } = useLoadPrTable(currentEndpoint);

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

/**
 * Start setting the scrollable height on any window resize change
 */
window.Vue.onMounted(() => {
    setScrollableHeight();
    window.addEventListener("resize", () => {
        setScrollableHeight();
    });
    // Set initial endpoint based on selected tab, which will trigger the load in useLoadPrTable
    currentEndpoint.value = props.selectedTab.endpoint;
});

// #endregion

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
                    :records-and-points="props.recordsAndPoints"
                    :sort-by="sortBy"
                    :sort-order="sortOrder"
                    @sort="onSortSelected"
                />
                <tbody>
                    <!-- Total row -->
                    <pr-table-total-row
                        :headers="(payload as TablePayload).data.tableHeaders"
                        :row-data="(payload as TablePayload).data.totalRow"
                        :records-or-points="recordsOrPoints"
                    />
                    <!-- All other rows -->
                    <!-- <pr-table-row
                        v-for="(row, index) in (payload as TablePayload).data.rows"
                        :key="`row-${index}`"
                        :headers="(payload as TablePayload).data.tableHeaders"
                        :row-data="row"
                        :records-or-points="recordsOrPoints"
                    /> -->
                </tbody>
            </table>
        </div>
        <!-- Table metadata -->
        <!-- <div class="pr-table-metadata">
            <div class="pr-table-metadata__count">
                Showing {{ (payload as TablePayload).data.rows.length }} of
                {{ (payload as TablePayload).metadata.count }} records
            </div>
        </div> -->
    </template>
</template>

<style scoped lang="scss">
.scrollable {
    overflow-y: auto;
}

.pr-table {
    width: 100%;
    thead {
        /* fix the head to stay at the top when scrolling */
        position: sticky;
        top: 0;
        z-index: var(--sbx-depth-ui-sticky);
        background: #ffffff; /* Ensure the header has a background to prevent transparency */
    }
}

.pr-table-metadata {
    margin-top: 1rem;
    padding: 0.5rem;
    border-top: 1px solid #e0e0e0;
    background-color: #f9f9f9;
    &__count {
        font-size: 0.9rem;
        color: #666;
    }
}
</style>
