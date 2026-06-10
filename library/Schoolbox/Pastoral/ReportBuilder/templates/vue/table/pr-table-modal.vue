<script setup lang="ts">
/**
 * To display the records linked to a particular cell when selected
 */

import { useTemplateRef, toRefs, type Ref } from "vue";
import { useScroll } from "@vueuse/core";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import PastoralRecord from "@schoolboxLibrary/Pastoral/vue/pastoral-record.vue";
import { useLoadRecords } from "@schoolboxLibrary/Pastoral/vue/composable/useLoadRecords";
import type { TableHeader, SelectedCell, Tab } from "../../js/types/pastoralReportTypes";
import { ScopeUrlParam } from "../../js/types/pastoralReportTypes";
import { usePrintRecords } from "../composable/usePrintRecords";
import PrGeneratePrint from "../pr-generate-print.vue";

const props = defineProps<{
    selectedCell: SelectedCell | null;
    recordsOrPoints: "records" | "points";
}>();

const selectedTab = window.Vue.inject<Ref<Tab>>("selectedTab");
const title = window.Vue.inject<string>("title");
const isOpen = window.Vue.ref(false);
const selectedHeader = window.Vue.ref<TableHeader | null>(null);
const scopeUrl = window.Vue.ref("");
const scopeUrlParams = new URLSearchParams();
const { isNewLoad, isNewLoading, isMoreLoading, payload, loadError } = useLoadRecords(scopeUrl);

/**
 * Get the current scope export url to download the related records as CSV
 * @returns - the current scope export URL
 */
function scopeExportUrl(): string {
    return selectedTab?.value._links.scopeExport
        ? `${selectedTab?.value._links.scopeExport}?${scopeUrlParams.toString()}`
        : "";
}

/**
 * Watch for changes in the selected cell and update the modal accordingly
 */
window.Vue.watch(
    () => props.selectedCell,
    (newCell) => {
        if (newCell) {
            selectedHeader.value = newCell.header;
            // clear out any existing row params
            const paramsToDelete = Array.from(scopeUrlParams.keys()).filter((key) =>
                key.includes(ScopeUrlParam.ROW as string),
            );
            paramsToDelete.forEach((param) => scopeUrlParams.delete(param));
            scopeUrlParams.delete(ScopeUrlParam.CURSOR as string); // Clear the cursor to start fresh
            // now start setting the new ones
            scopeUrlParams.set(ScopeUrlParam.COLUMN as string, selectedHeader.value.key);
            scopeUrlParams.set(ScopeUrlParam.SELECTED as string, props.recordsOrPoints); // set whether to show records or points
            scopeUrlParams.set(ScopeUrlParam.LIMIT as string, "20"); // set initial pagination limit for modal records
            if (newCell.rowKeys !== undefined) {
                // loop through the RowKeys object and set any any params that are provided
                Object.entries(newCell.rowKeys as Record<string, number>).forEach(([key, value]) => {
                    if (value !== undefined) {
                        scopeUrlParams.set(`${ScopeUrlParam.ROW}${key}`, value.toString());
                    }
                });
            }
            scopeUrl.value = `${selectedTab?.value._links.scope}?${scopeUrlParams.toString()}`;
            isOpen.value = true; // Open the modal to show related records
        } else {
            isOpen.value = false; // Close the modal when no cell is selected
        }
    },
);

/**
 * To display more results when there's lots to display and the user has scrolled to the bottom
 */
const modalResultsEl = useTemplateRef<HTMLDivElement>("modalResultsRef");
const { arrivedState } = useScroll(modalResultsEl, {
    offset: { bottom: 30 },
});

const { bottom } = toRefs(arrivedState);
window.Vue.watch(bottom, (isAtBottom) => {
    if (isAtBottom && payload.value && payload.value.metadata && payload.value.metadata.cursor.next !== null) {
        scopeUrlParams.set(ScopeUrlParam.CURSOR as string, payload.value.metadata.cursor.next);
        scopeUrl.value = `${selectedTab?.value._links.scope}?${scopeUrlParams.toString()}`;
    }
});

/**
 * Print all records in the list modal
 */
const containerEl = window.Vue.useTemplateRef<HTMLDivElement>("containerRef");
const { printAllRecords, isLoading: isLoadingPrint } = usePrintRecords(
    scopeUrl,
    scopeUrlParams,
    payload,
    containerEl,
    selectedTab?.value._links.scope,
);
</script>

<template>
    <basic-modal v-model:is-open="isOpen" has-background-click allow-outside-click>
        <div v-if="isNewLoad && isNewLoading" class="loader"></div>
        <data-load-error v-else-if="isNewLoad && loadError" data-title="record" :further-info="loadError" />
        <template v-else-if="selectedHeader !== null">
            <div ref="containerRef" class="container l-flex-col l-gap-m">
                <div class="l-flex-row l-flex--justify-space l-flex--no-wrap l-gap-m">
                    <div class="l-flex-col l-gap-s f-no-margins">
                        <h2>{{ title }}</h2>
                        <p v-if="props.selectedCell" class="meta">
                            <template v-for="(meta, index) in props.selectedCell.meta" :key="index">
                                {{ meta }}<br />
                            </template>
                        </p>
                    </div>
                    <nav class="l-flex-row l-flex--align-center f-no-margins">
                        <button
                            class="sbx-icon-only-button f-no-margins"
                            aria-label="Print Report Results"
                            title="Print Report Results"
                            type="button"
                            @click="printAllRecords"
                        >
                            <sbx-icon name="print" />
                        </button>
                        <a
                            v-if="selectedTab?._links.scopeExport"
                            class="sbx-icon-only-button f-no-margins"
                            :href="scopeExportUrl()"
                            aria-label="Download related records as CSV"
                            title="Download related records as CSV"
                            ><sbx-icon name="download"
                        /></a>
                    </nav>
                </div>
                <div ref="modalResultsRef" class="pr-table__records" data-test="pr-table-modal-records">
                    <div class="l-flex-col l-gap-s">
                        <div v-for="record in payload?.data.results" :key="record.id" class="c-island">
                            <ul class="information-list threaded" data-test="pastoral-records-modal-list">
                                <pastoral-record :record="record" />
                            </ul>
                        </div>
                    </div>
                    <div v-if="isMoreLoading" class="c-island l-pad-s">
                        <div class="loader"></div>
                    </div>
                    <data-load-error
                        v-else-if="!isNewLoad && loadError"
                        data-title="record"
                        :further-info="loadError"
                    />
                </div>
            </div>
        </template>
    </basic-modal>
    <pr-generate-print v-model:is-loading="isLoadingPrint" :load-error="loadError" />
</template>

<style scoped>
.pr-table__records {
    max-height: 650px;
    overflow-y: auto;
}

@media print {
    .container {
        padding-top: 1rem; /* to account for the pr-generate-print still possibly being visible due to animating off */
    }
    .pr-table__records {
        max-height: none !important; /* Expand scrollable elements to full height in print view */
        overflow: visible !important; /* Ensure all content is visible */
    }
}
</style>
