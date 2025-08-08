<script setup lang="ts">
/**
 * To display the records linked to a particular cell when selected
 */

import { useTemplateRef, toRefs } from "vue";
import { useScroll } from "@vueuse/core";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import { useStore } from "../store/pastoralReportStore";
import type { Tab, TableHeader, SelectedCell } from "../../js/types/pastoralReportTypes";
import { ScopeUrlParam } from "../../js/types/pastoralReportTypes";
import { useLoadRecords } from "../composable/useLoadRecords";
import PrRecord from "../pr-record.vue";

const props = defineProps<{
    selectedTab: Tab;
    selectedCell: SelectedCell | null;
}>();

const store = useStore();
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
    return props.selectedTab._links.scopeExport
        ? `${props.selectedTab._links.scopeExport}?${scopeUrlParams.toString()}`
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
            scopeUrlParams.set(ScopeUrlParam.COLUMN as string, selectedHeader.value.key);
            if (newCell.rowId !== undefined) {
                scopeUrlParams.set(ScopeUrlParam.ROW as string, newCell.rowId.toString());
            } else {
                scopeUrlParams.delete(ScopeUrlParam.ROW as string);
            }
            scopeUrlParams.delete(ScopeUrlParam.CURSOR as string); // Clear the cursor to start fresh
            scopeUrl.value = `${props.selectedTab._links.scope}?${scopeUrlParams.toString()}`;
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
    if (isAtBottom && payload.value && payload.value.metadata.cursor.next !== null) {
        scopeUrlParams.set("cursor", payload.value.metadata.cursor.next);
        scopeUrl.value = `${props.selectedTab._links.scope}?${scopeUrlParams.toString()}`;
    }
});
</script>

<template>
    <basic-modal v-model:is-open="isOpen" has-background-click>
        <div v-if="isNewLoad && isNewLoading" class="loader"></div>
        <data-load-error v-else-if="isNewLoad && loadError" data-title="record" :further-info="loadError" />
        <template v-else-if="selectedHeader !== null">
            <div class="l-flex-col l-gap-m">
                <div class="l-flex-row l-flex--justify-space l-gap-s">
                    <div class="l-flex-col l-gap-s f-no-margins">
                        <h2>{{ store.title }}</h2>
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
                    <div class="l-flex-col l-gap-s">
                        <div v-for="record in payload?.data.results" :key="record.id" class="c-island">
                            <ul class="information-list threaded">
                                <pr-record :record="record" />
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
</template>

<style scoped>
.pr-table__records {
    max-height: 650px;
    overflow-y: auto;
}
</style>
