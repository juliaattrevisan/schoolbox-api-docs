<script setup lang="ts">
/**
 * Manage Customisation Records page
 */

import type { UserCC as User } from "@schoolboxLibrary/types/User";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import CursorPagination from "@schoolboxVue/components/pagination/cursor-pagination.vue";
import LazyTable from "@schoolboxVue/components/table/lazyTable/lazy-table.vue";
import type { Header, Routes, BasicType } from "@schoolboxVue/components/cell/cellTypes/BasicType";
import CellUser from "@schoolboxVue/components/cell/cell-user.vue";
import CellDate from "@schoolboxVue/components/cell/cell-date.vue";
import CellBasic from "@schoolboxVue/components/cell/cell-basic.vue";
import { useRowsStore } from "@schoolboxVue/components/table/lazyTable/store/ltRowsStore";
import { useMaxScrollableHeight } from "@schoolboxVue/composables/content/useMaxScrollableHeight";
import { useHeaderStore } from "./store/crHeaderStore";

const props = defineProps<{
    header: Header[];
    routes: Routes;
}>();

const headerStore = useHeaderStore();
headerStore.init(props.header);
const rowsStore = useRowsStore();
rowsStore.init(headerStore.header, props.routes.getData);

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
        <p>No Customisation Records exist. They will appear here once records have been created.</p>
    </empty-state>
    <template v-else-if="rowsStore.hasRows && rowsStore.payload">
        <div ref="scrollableRef" class="scrollable" :style="{ maxHeight: maxScrollableHeight }">
            <lazy-table
                :header="headerStore.header"
                :rows="rowsStore.payload.data"
                :is-loading="rowsStore.isMoreLoading"
                :sort-by="rowsStore.sortBy"
                :order="rowsStore.order"
                @sort="rowsStore.onSort"
            >
                <template #User="{ row, header: columnHeader }">
                    <cell-user :user="row[columnHeader.key] as User" />
                </template>
                <template #DateTime="{ row, header: columnHeader }">
                    <cell-date :value="row[columnHeader.key] as string" />
                </template>
                <template #Basic="{ row, header: columnHeader }">
                    <cell-basic :value="row[columnHeader.key] as BasicType" />
                </template>
            </lazy-table>
            <cursor-pagination
                :url="rowsStore.url"
                :metadata="rowsStore.payload.metadata"
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
                    class="l-flex-row l-gap-s f-no-bkg f-no-margins"
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
