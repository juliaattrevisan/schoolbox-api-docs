<script setup lang="ts">
/**
 * Manage Differentiation Profiles page
 */

import locale from "@js/terminology/rosettaStone";
import type { UserCC as User } from "@schoolboxLibrary/types/User";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import CursorPagination from "@schoolboxVue/components/pagination/cursor-pagination.vue";
import LazyGrid from "@schoolboxVue/components/grid/lazyGrid/lazy-grid.vue";
import DummyInlineEditor from "@schoolboxVue/components/ckeditor/dummy-inline-editor.vue";
import { useMaxScrollableHeight } from "@schoolboxVue/composables/content/useMaxScrollableHeight";
import PastoralRecordsModal from "@schoolboxLibrary/Pastoral/vue/pastoral-records-modal.vue";
import type { Routes as PastoralRoutes, Config } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import type {
    Routes,
    MDPHeader,
    AdjustmentType,
    ProfileAdjustmentKeys,
    ProfileTextKeys,
    Payload,
    Profile,
} from "../js/types/manageDifferentiationProfilesTypes";
import MdpGridCellStudent from "./grid/cell/mdp-grid-cell-student.vue";
import MdpGridCellAdjustment from "./grid/cell/mdp-grid-cell-adjustment.vue";
import MdpGridCellText from "./grid/cell/mdp-grid-cell-text.vue";
import MdpGridCellLastUpdated from "./grid/cell/mdp-grid-cell-last-updated.vue";
import MdpAiModal from "./mdp-ai-modal.vue";
import { useHeaderStore } from "./store/mdpHeaderStore";
import { useRowsStore } from "./store/mdpRowsStore";
import { useLinkedRecordsStore } from "./store/mdpLinkedRecordsStore";
import { useGenAiStore } from "./store/mdpGenAiStore";

const props = defineProps<{
    header: MDPHeader[];
    adjustmentCategories: AdjustmentType[];
    adjustmentLevels: AdjustmentType[];
    routes: Routes;
    pastoralConfig: Config;
    pastoralRoutes: PastoralRoutes;
}>();

window.Vue.provide("pastoralConfig", props.pastoralConfig);
window.Vue.provide("pastoralRoutes", props.pastoralRoutes);

const headerStore = useHeaderStore();
headerStore.init(props.header);
const rowsStore = useRowsStore();
rowsStore.init(headerStore.header, props.routes.getData);
const linkedRecordsStore = useLinkedRecordsStore();
const genAiStore = useGenAiStore();
genAiStore.init(props.routes.genAi);

/**
 * Keep the container below the height of the viewport
 */
const minWidth = 700; // Minimum width where useMaxScrollableHeight is applied
const scrollableEl = window.Vue.useTemplateRef<HTMLDivElement>("scrollableRef");
const { maxScrollableHeight } = useMaxScrollableHeight(
    scrollableEl,
    minWidth,
    [document.getElementById("filter_panel")],
    500,
);
</script>

<template>
    <div v-if="rowsStore.isNewLoading" class="c-island l-pad-m">
        <div class="loader"></div>
    </div>
    <div v-else-if="rowsStore.loadError" class="c-island l-pad-m">
        <data-load-error data-title="record" :further-info="rowsStore.loadError" />
    </div>
    <empty-state v-else-if="rowsStore.isEmpty" icon="icon-learning-support">
        <p>
            No {{ locale.differentiationProfile.translate(true, true) }} exist. They will appear here once records have
            been linked.
        </p>
    </empty-state>
    <template v-else-if="rowsStore.hasRows && rowsStore.payload">
        <div ref="scrollableRef" class="scrollable" :style="{ maxHeight: maxScrollableHeight }">
            <lazy-grid
                :header="headerStore.header"
                :rows="rowsStore.payload.data.profiles"
                :is-loading="rowsStore.isMoreLoading"
                :sort-by="rowsStore.sortBy"
                :order="rowsStore.order"
                @sort="rowsStore.onSort"
            >
                <template #User="{ row, header: columnHeader }">
                    <mdp-grid-cell-student :user="row[columnHeader.key] as User" :profile="row as Profile" />
                </template>
                <template #Adjustment="{ row, header: columnHeader }">
                    <mdp-grid-cell-adjustment
                        :profile="row as Profile"
                        :header-key="columnHeader.key as ProfileAdjustmentKeys"
                        :adjustments="row[columnHeader.key] as AdjustmentType[]"
                        :options="
                            columnHeader.key === 'adjustment_category'
                                ? props.adjustmentCategories
                                : props.adjustmentLevels
                        "
                    />
                </template>
                <template #Text="{ row, header: columnHeader }">
                    <!-- ignore is added below to avoid the brackets being removed from :text, which causes a vue/no-deprecated-filter warning *_* -->
                    <!-- prettier-ignore -->
                    <mdp-grid-cell-text
                    :profile="row as Profile"
                    :header-key="columnHeader.key as ProfileTextKeys"
                    :text="(row[columnHeader.key] as string | null)"
                />
                </template>
                <template #Date="{ row }">
                    <mdp-grid-cell-last-updated :profile="row as Profile" />
                </template>
            </lazy-grid>
            <loading-table v-if="rowsStore.isMoreLoading" :rows="5" row-height="3rem" />
            <cursor-pagination
                :url="rowsStore.url"
                :metadata="(rowsStore.payload as Payload).metadata"
                :limit="rowsStore.limit"
                :is-loading="rowsStore.isNewLoading || rowsStore.isMoreLoading"
                @previous="rowsStore.onPaginate"
                @next="rowsStore.onPaginate"
            />
        </div>
        <template v-if="props.routes.exportCsv">
            <teleport to="#download-csv-container">
                <a
                    href="#export-to-csv"
                    class="l-flex l-gap-s f-no-bkg f-no-margins"
                    aria-label="Export to CSV"
                    title="Export to CSV"
                    @click.prevent="rowsStore.exportToCSV(props.routes.exportCsv)"
                >
                    <sbx-icon name="export" />
                    Export to CSV
                </a>
            </teleport>
        </template>
    </template>
    <pastoral-records-modal
        v-model:url="linkedRecordsStore.url"
        :title="linkedRecordsStore.title"
        :meta="linkedRecordsStore.meta"
    />
    <mdp-ai-modal />
    <dummy-inline-editor />
</template>

<style scoped></style>

<style>
/* A few tweaks to adjust the filter panel to sit properly within the layout */
#filter_panel {
    padding: 0;
}

#filter_panel section {
    margin-bottom: 0;
}
</style>
