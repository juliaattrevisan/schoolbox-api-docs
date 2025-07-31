<script setup lang="ts">
/**
 * Displays a pastoral report in a table format.
 */

import { useTemplateRef } from "vue";
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
const viewLink = window.Vue.ref("");
const viewLinkParams = new URLSearchParams();
const LIMIT = 20; // the current set limit for the number of records to fetch at once
const sortBy = window.Vue.ref<string>("user"); // the current sort by field - this should update when payload is loaded
const order = window.Vue.ref<"ASC" | "DESC">("ASC"); // the current sort order
const { isNewLoading, isSameLoading, payload, isEmpty, loadError } = useLoadPrTable(viewLink);

/**
 * Reset the table load by clearing the cursor and reloading the data
 */
function resetTableLoad(): void {
    viewLinkParams.set("selected", recordsOrPoints.value);
    viewLinkParams.set("cursor", "");
    viewLinkParams.set("limit", LIMIT.toString());
    viewLinkParams.set("sortBy", sortBy.value);
    viewLinkParams.set("order", order.value);
    viewLink.value = `${props.selectedTab._links.view}?${viewLinkParams.toString()}`;
}

/**
 * Load the row data again when the recordsOrPoints changes
 */
window.Vue.watch(recordsOrPoints, () => {
    viewLinkParams.set("selected", recordsOrPoints.value);
    viewLink.value = `${props.selectedTab._links.view}?${viewLinkParams.toString()}`;
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
    viewLinkParams.set("sortBy", sortBy.value);
    viewLinkParams.set("order", order.value);
    viewLinkParams.set("cursor", ""); // Reset cursor on sort change
    viewLink.value = `${props.selectedTab._links.view}?${viewLinkParams.toString()}`;
}

/**
 * Handle previous/next page button clicks
 * @param cursor - the cursor to use for the previous/next page
 */
function onPaginate(cursor: string): void {
    viewLinkParams.set("cursor", cursor);
    viewLink.value = `${props.selectedTab._links.view}?${viewLinkParams.toString()}`;
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
const scopeLink = window.Vue.ref("");
const scopeLinkParams = new URLSearchParams();
const { isLoading: isLoadingRecords, payload: recordsPayload, loadError: recordsLoadError } = useLoadRecords(scopeLink);

/**
 * Handle cell selection
 * @param header - the header of the cell that was selected
 */
function onCellSelect(header: TableHeader): void {
    selectedHeader.value = header;
    scopeLinkParams.set("column", selectedHeader.value.key);
    scopeLink.value = `${props.selectedTab._links.scope}?${scopeLinkParams.toString()}`;
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
                :endpoint="viewLink"
                :metadata="(payload as TablePayload).metadata"
                :limit="LIMIT"
                @previous="onPaginate"
                @next="onPaginate"
            />
        </div>
    </template>
    <basic-modal v-model:is-open="isCellExpanded">
        <div v-if="isLoadingRecords" class="loader"></div>
        <div v-else-if="recordsLoadError" class="alert-box alert f-no-margins">
            Sorry, there was an error during record retrieval. Please
            <a href="" title="refresh the page"> refresh the page </a>
            to try again and contact your support if the problem persists.
            <template v-if="recordsLoadError && recordsLoadError.length > 0"
                ><em>Further support info: {{ recordsLoadError }}</em></template
            >
        </div>
        <template v-else-if="selectedHeader !== null">
            <div class="l-flex-col l-gap-m">
                <div class="l-flex-col l-gap-s f-no-margins">
                    <h2>{{ props.title }}</h2>
                    <p class="meta">
                        Type: {{ selectedHeader.title }}
                        <template v-if="selectedHeader.subTitle"
                            ><br />Sub-Type: {{ selectedHeader.subTitle }}</template
                        >
                    </p>
                </div>
                <div class="pr-table__records">
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
}
</style>
