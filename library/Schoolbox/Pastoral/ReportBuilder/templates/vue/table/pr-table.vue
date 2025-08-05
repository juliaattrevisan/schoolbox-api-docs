<script setup lang="ts">
/**
 * Displays a pastoral report in a table format.
 */

import { useTemplateRef, toRefs } from "vue";
import { useScroll } from "@vueuse/core";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type {
    Tab,
    TablePayload,
    Config,
    Routes,
    SeverityColors,
    TableHeader,
} from "../../js/types/pastoralReportTypes";
import { TabType } from "../../js/types/pastoralReportTypes";
import { useLoadPrTable } from "../composable/useLoadPrTable";
import { useLoadRecords } from "../composable/useLoadRecords";
import PrTableHeader from "./pr-table-header.vue";
import PrTableTotalRow from "./pr-table-total-row.vue";
import PrTableRow from "./pr-table-row.vue";
import PrTableFooter from "./pr-table-footer.vue";
import PrRecord from "../pr-record.vue";

const props = defineProps<{
    title: string;
    selectedTab: Tab;
    config: Config;
    routes: Routes;
    recordsAndPoints: string[];
    localePastoral: string;
    showSeverity: boolean;
    severityColors: SeverityColors;
}>();

// #region scrollable table height

const resizeObserver = window.Vue.ref<ResizeObserver | null>(null);
const maxScrollableHeight = window.Vue.ref<string>(`${window.innerHeight}px`);

/**
 * Set the max height to allow the table to sit within the browser window
 */
function setScrollableHeight(): void {
    const tabsBottomY = document.querySelector("dl")?.getBoundingClientRect().bottom || 0;
    const footerHeight = document.getElementById("footer")?.getBoundingClientRect().height || 0;

    const mainSBContainerEl = document.getElementById("container");
    const mainSBContainerPaddingBtm = mainSBContainerEl
        ? parseInt(window.getComputedStyle(mainSBContainerEl).paddingBottom, 10)
        : 0;

    const newHeight = window.innerHeight - tabsBottomY - footerHeight - mainSBContainerPaddingBtm - 1;

    maxScrollableHeight.value = `${newHeight}px`;
}

/**
 * Initialize the ResizeObserver to adjust the scrollable height
 * when the container changes size
 */
function initSetScrollableHeight(): void {
    resizeObserver.value = new ResizeObserver(setScrollableHeight);
    const containerEl = document.querySelector("#container");
    if (containerEl) {
        resizeObserver.value.observe(containerEl);
    }
    // Set the initial height, and update it again when the DOM is fully loaded to ensure the footer is included
    setScrollableHeight();
    window.addEventListener("DOMContentLoaded", setScrollableHeight, { once: true });
}

// #endregion

// #region table load

const recordsOrPoints = defineModel<"records" | "points">("records-or-points", { default: "records" });
const viewUrl = window.Vue.ref("");
const viewUrlParams = new URLSearchParams();
const LIMIT = 20; // the current set limit for the number of records to fetch at once
const sortBy = window.Vue.ref<string>("user"); // the current sort by field - this should update when payload is loaded
const order = window.Vue.ref<"ASC" | "DESC">("ASC"); // the current sort order
const { isNewLoading, isMoreLoading, payload, isEmpty, hasRows, loadError } = useLoadPrTable(viewUrl);

/**
 * Reset the table load by clearing the cursor and reloading the data
 */
function resetTableLoad(): void {
    viewUrlParams.set("selected", recordsOrPoints.value);
    viewUrlParams.set("cursor", "");
    viewUrlParams.set("limit", LIMIT.toString());
    viewUrlParams.set("sortBy", sortBy.value);
    viewUrlParams.set("order", order.value);
    viewUrl.value = `${props.selectedTab._links.view}?${viewUrlParams.toString()}`;
}

/**
 * Load the row data again when the recordsOrPoints changes
 */
window.Vue.watch(recordsOrPoints, () => {
    viewUrlParams.set("selected", recordsOrPoints.value);
    viewUrl.value = `${props.selectedTab._links.view}?${viewUrlParams.toString()}`;
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
    viewUrlParams.set("sortBy", sortBy.value);
    viewUrlParams.set("order", order.value);
    viewUrlParams.set("cursor", ""); // Reset cursor on sort change
    viewUrl.value = `${props.selectedTab._links.view}?${viewUrlParams.toString()}`;
}

/**
 * Handle previous/next page button clicks
 * @param cursor - the cursor to use for the previous/next page
 */
function onPaginate(cursor: string): void {
    viewUrlParams.set("cursor", cursor);
    viewUrl.value = `${props.selectedTab._links.view}?${viewUrlParams.toString()}`;
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
const selectedHeader = window.Vue.ref<TableHeader | null>(null);
const scopeUrl = window.Vue.ref("");
const scopeUrlParams = new URLSearchParams();
const {
    isNewLoad: isNewRecordLoad,
    isNewLoading: isLoadingNewRecords,
    isMoreLoading: isLoadingMoreRecords,
    payload: recordsPayload,
    loadError: recordsLoadError,
} = useLoadRecords(scopeUrl);

/**
 * Get the current scope export url to download the related records as CSV
 * @returns - the current scope export URL
 */
function scopeExportUrl(): string {
    return props.selectedTab._links.scopeExport
        ? `${props.selectedTab._links.scopeExport}?${scopeUrlParams.toString()}`
        : "";
}

/**
 * Handle total cell selection
 * @param header - the header of the cell that was selected
 */
function onTotalCellSelect(header: TableHeader): void {
    selectedHeader.value = header;
    scopeUrlParams.set("column", selectedHeader.value.key);
    scopeUrlParams.delete("row");
    scopeUrlParams.delete("cursor");
    scopeUrl.value = `${props.selectedTab._links.scope}?${scopeUrlParams.toString()}`;
    isCellExpanded.value = true; // Open the modal to show related records
}

/**
 * Handle row cell selection
 * @param header - the header of the cell that was selected
 * @param rowId - the id of the row that was selected
 */
function onRowCellSelect(header: TableHeader, rowId: number): void {
    selectedHeader.value = header;
    scopeUrlParams.set("column", selectedHeader.value.key);
    scopeUrlParams.set("row", rowId.toString());
    scopeUrlParams.delete("cursor");
    scopeUrl.value = `${props.selectedTab._links.scope}?${scopeUrlParams.toString()}`;
    isCellExpanded.value = true; // Open the modal to show related records
}

/**
 * To display more results when there's lots to display and the user has scrolled to the bottom
 */
const modalResultsEl = useTemplateRef<HTMLDivElement>("modalResultsRef");
const { arrivedState } = useScroll(modalResultsEl, {
    offset: { bottom: 30 },
});
const { bottom } = toRefs(arrivedState);
window.Vue.watch(bottom, (isAtBottom) => {
    if (isAtBottom && recordsPayload.value && recordsPayload.value.metadata.cursor.next !== null) {
        scopeUrlParams.set("cursor", recordsPayload.value.metadata.cursor.next);
        scopeUrl.value = `${props.selectedTab._links.scope}?${scopeUrlParams.toString()}`;
    }
});

// #endregion

/**
 * Start setting the scrollable height on any window resize change
 * and reset the table load to ensure the correct endpoint is set
 */
window.Vue.onMounted(() => {
    initSetScrollableHeight();
    resetTableLoad();
});

/**
 * Clean up the ResizeObserver when the component is unmounted
 */
window.Vue.onUnmounted(() => {
    if (resizeObserver.value) {
        resizeObserver.value.disconnect();
        resizeObserver.value = null;
    }
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
    <div v-else-if="isEmpty" class="empty-state-flex-centered f-no-margins">
        <i class="icon-pastoral"></i>
        <p>No {{ props.localePastoral }} records matched your report criteria.</p>
        <a :href="props.routes.getIndex"> Select Another Report </a>
    </div>
    <template v-else-if="hasRows">
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
                    :config="props.config"
                    :records-and-points="props.recordsAndPoints"
                    :sort-by="sortBy"
                    :order="order"
                    @sort="onSort"
                />
                <!-- Skeleton loading state when switching between records/rows and sorting -->
                <tbody v-if="isMoreLoading" class="skeleton">
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
                        @cell-select="onTotalCellSelect"
                    />
                    <!-- All other rows -->
                    <pr-table-row
                        v-for="(row, index) in (payload as TablePayload).data.rows"
                        :key="`row-${index}`"
                        :headers="(payload as TablePayload).data.tableHeaders"
                        :row-data="row"
                        :records-or-points="recordsOrPoints"
                        @cell-select="onRowCellSelect"
                    />
                </tbody>
            </table>
            <pr-table-footer
                :endpoint="viewUrl"
                :metadata="(payload as TablePayload).metadata"
                :limit="LIMIT"
                @previous="onPaginate"
                @next="onPaginate"
            />
        </div>
    </template>
    <basic-modal v-model:is-open="isCellExpanded" has-background-click>
        <div v-if="isNewRecordLoad && isLoadingNewRecords" class="loader"></div>
        <div v-else-if="isNewRecordLoad && recordsLoadError" class="alert-box alert f-no-margins">
            Sorry, there was an error during further record retrieval. Please
            <a href="" title="refresh the page"> refresh the page </a>
            to try again and contact your support if the problem persists.
            <template v-if="recordsLoadError && recordsLoadError.length > 0"
                ><em>Further support info: {{ recordsLoadError }}</em></template
            >
        </div>
        <template v-else-if="selectedHeader !== null">
            <div class="l-flex-col l-gap-m">
                <div class="l-flex-row l-flex--justify-space l-gap-s">
                    <div class="l-flex-col l-gap-s f-no-margins">
                        <h2>{{ props.title }}</h2>
                        <p class="meta">
                            <template v-if="selectedHeader.title !== 'Total'"
                                >Type: {{ selectedHeader.title }}</template
                            >
                            <template v-if="selectedHeader.subTitle"
                                ><br />Sub-Type: {{ selectedHeader.subTitle }}</template
                            >
                        </p>
                    </div>
                    <a
                        v-if="props.selectedTab._links.scopeExport"
                        :href="scopeExportUrl()"
                        aria-label="Download related records as CSV"
                        title="Download related records as CSV"
                        ><sbx-icon name="download"
                    /></a>
                </div>
                <div ref="modalResultsRef" class="pr-table__records">
                    <section v-for="record in recordsPayload?.data.results" :key="record.id">
                        <ul class="information-list threaded">
                            <pr-record
                                :record="record"
                                :config="props.config"
                                :routes="props.routes"
                                :locale-pastoral="props.localePastoral"
                                :show-severity="props.showSeverity"
                                :severity-colors="props.severityColors"
                            />
                        </ul>
                    </section>
                    <div v-if="isLoadingMoreRecords" class="c-island l-pad-s">
                        <div class="loader"></div>
                    </div>
                    <div v-else-if="!isNewRecordLoad && recordsLoadError" class="alert-box alert f-no-margins">
                        Sorry, there was an error during record retrieval. Please
                        <a href="" title="refresh the page"> refresh the page </a>
                        to try again and contact your support if the problem persists.
                        <template v-if="recordsLoadError && recordsLoadError.length > 0"
                            ><em>Further support info: {{ recordsLoadError }}</em></template
                        >
                    </div>
                </div>
            </div>
        </template>
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

.pr-table__records {
    max-height: 650px;
    overflow-y: auto;
    section {
        margin-bottom: 1rem;
    }
}
</style>
