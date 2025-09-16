<script setup lang="ts">
/**
 * Displays a pastoral report in a table format.
 */

import type { Ref } from "vue";
import { useElementSize } from "@vueuse/core";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import type { TablePayload, TableHeader, SelectedCell, RowKeys, Tab } from "../../js/types/pastoralReportTypes";
import { TabType, ViewUrlParam } from "../../js/types/pastoralReportTypes";
import { useLoadPrTable } from "../composable/useLoadPrTable";
import { useMaxScrollableHeight } from "../composable/useMaxScrollableHeight";
import PrEmpty from "../pr-empty.vue";
import PrTableHeader from "./pr-table-header.vue";
import PrTableTotalRow from "./pr-table-total-row.vue";
import PrTableRow from "./pr-table-row.vue";
import PrTableFooter from "./pr-table-footer.vue";
import PrTableModal from "./pr-table-modal.vue";

// #region sticky table columns for bigger screens

const containerEl = window.Vue.useTemplateRef<HTMLDivElement>("containerRef");
const { width: containerWidth } = useElementSize(containerEl);
const isTotalSticky = window.Vue.computed(() => containerWidth.value > 1200);

// #endregion

// #region table load / sorting / pagination

const selectedTab = window.Vue.inject<Ref<Tab>>("selectedTab");
const recordsOrPoints = defineModel<"records" | "points">("records-or-points", { default: "records" });
const viewUrl = window.Vue.ref("");
const viewUrlParams = new URLSearchParams();
const LIMIT = 20; // the current set limit for the number of records to fetch at once
const order = window.Vue.ref<"ASC" | "DESC">("ASC"); // the current sort order
const { isNewLoading, isMoreLoading, payload, sortBy, isEmpty, hasRows, loadError } = useLoadPrTable(viewUrl);

/**
 * Reset the table load by clearing the cursor and reloading the data
 */
function resetTableLoad(): void {
    viewUrlParams.set(ViewUrlParam.SELECTED as string, recordsOrPoints.value);
    viewUrlParams.set(ViewUrlParam.CURSOR as string, "");
    viewUrlParams.set(ViewUrlParam.LIMIT as string, LIMIT.toString());
    viewUrlParams.set(ViewUrlParam.SORT_BY as string, sortBy.value);
    viewUrlParams.set(ViewUrlParam.ORDER as string, order.value);
    viewUrl.value = `${selectedTab?.value._links.view}?${viewUrlParams.toString()}`;
}

/**
 * Load the row data again when the recordsOrPoints changes
 */
window.Vue.watch(recordsOrPoints, () => {
    viewUrlParams.set(ViewUrlParam.SELECTED as string, recordsOrPoints.value);
    viewUrlParams.set(ViewUrlParam.CURSOR as string, ""); // Reset cursor on records/points change
    viewUrl.value = `${selectedTab?.value._links.view}?${viewUrlParams.toString()}`;
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
    viewUrlParams.set(ViewUrlParam.SORT_BY as string, sortBy.value);
    viewUrlParams.set(ViewUrlParam.ORDER as string, order.value);
    viewUrlParams.set(ViewUrlParam.CURSOR as string, ""); // Reset cursor on sort change
    viewUrl.value = `${selectedTab?.value._links.view}?${viewUrlParams.toString()}`;
}

/**
 * Handle previous/next page button clicks
 * @param cursor - the cursor to use for the previous/next page
 */
function onPaginate(cursor: string): void {
    viewUrlParams.set(ViewUrlParam.CURSOR as string, cursor);
    viewUrl.value = `${selectedTab?.value._links.view}?${viewUrlParams.toString()}`;
}

/**
 * when the endpoint changes trigger a new load
 * we can assert the type of the new tab as pr-table wont exist
 * without selectedTab being set
 */
window.Vue.watch(selectedTab as Ref<Tab>, (newTab) => {
    if (newTab.type === TabType.TABLE) {
        resetTableLoad();
    }
});

// #endregion

// #region cell selection

const selectedCell = window.Vue.ref<SelectedCell | null>(null);

/**
 * Handle total cell selection
 * @param header - the header of the cell that was selected
 * @param meta - the metadata associated with the cell
 */
function onTotalCellSelect(header: TableHeader, meta: string[]): void {
    selectedCell.value = {
        header,
        meta,
    };
}

/**
 * Handle row cell selection
 * @param header - the header of the cell that was selected
 * @param rowKeys - the keys of the row that was selected
 * @param meta - the metadata associated with the cell
 */
function onRowCellSelect(header: TableHeader, rowKeys: RowKeys, meta: string[]): void {
    selectedCell.value = {
        header,
        rowKeys,
        meta,
    };
}

// #endregion

const scrollableEl = window.Vue.useTemplateRef<HTMLDivElement>("scrollableRef");
const { maxScrollableHeight } = useMaxScrollableHeight(scrollableEl);

/**
 * Reset the table load to ensure the correct endpoint is set
 */
window.Vue.onMounted(() => {
    resetTableLoad();
});
</script>

<template>
    <div v-if="isNewLoading" class="c-island l-pad-m" data-test="pr-table-loading">
        <div class="loader"></div>
    </div>
    <div v-else-if="loadError" class="c-island l-pad-m">
        <data-load-error data-title="record" :further-info="loadError" />
    </div>
    <pr-empty v-else-if="isEmpty" />
    <template v-else-if="hasRows">
        <div ref="containerRef">
            <div
                ref="scrollableRef"
                class="table-container scrollable"
                :style="{ maxHeight: maxScrollableHeight }"
                data-test="pr-table"
            >
                <table class="pr-table">
                    <pr-table-header
                        v-model:records-or-points="recordsOrPoints"
                        :headers="(payload as TablePayload).data.tableHeaders"
                        :sort-by="sortBy"
                        :order="order"
                        :is-total-sticky="isTotalSticky"
                        @sort="onSort"
                    />
                    <!-- Skeleton loading state when switching between records/rows and sorting -->
                    <loading-table v-if="isMoreLoading" :only-body="true" />
                    <tbody v-else>
                        <!-- Total row -->
                        <pr-table-total-row
                            :headers="(payload as TablePayload).data.tableHeaders"
                            :row-data="(payload as TablePayload).data.totalRow"
                            :records-or-points="recordsOrPoints"
                            :is-total-sticky="isTotalSticky"
                            @cell-select="onTotalCellSelect"
                        />
                        <!-- All other rows -->
                        <pr-table-row
                            v-for="(row, index) in (payload as TablePayload).data.rows"
                            :key="`row-${index}`"
                            :headers="(payload as TablePayload).data.tableHeaders"
                            :row-data="row"
                            :records-or-points="recordsOrPoints"
                            :is-total-sticky="isTotalSticky"
                            @cell-select="onRowCellSelect"
                        />
                    </tbody>
                </table>
                <pr-table-footer
                    :url="viewUrl"
                    :metadata="(payload as TablePayload).metadata"
                    :limit="LIMIT"
                    :is-loading="isNewLoading || isMoreLoading"
                    @previous="onPaginate"
                    @next="onPaginate"
                />
            </div>
        </div>
    </template>
    <!-- fall back to just an empty state if all else fails -->
    <pr-empty v-else />
    <pr-table-modal :selected-cell="selectedCell" :records-or-points="recordsOrPoints" />
</template>

<style scoped lang="scss">
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
</style>

<style lang="scss">
.highcharts-tooltip-container {
    z-index: var(--sbx-depth-pop-over) !important;
}
</style>
