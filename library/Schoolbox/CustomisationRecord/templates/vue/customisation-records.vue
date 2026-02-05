<script setup lang="ts">
/**
 * Manage Customisation Records page
 */

import type { UserCC as User } from "@schoolboxLibrary/types/User";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import LazyGrid from "@schoolboxVue/components/grid/lazyGrid/lazy-grid.vue";
import type { Header, Routes } from "@schoolboxVue/components/grid/lazyGrid/types/lazyGridTypes";
import LgUserCell from "@schoolboxVue/components/grid/lazyGrid/cellTypes/lg-user-cell.vue";
import LgDateCell from "@schoolboxVue/components/grid/lazyGrid/cellTypes/lg-date-cell.vue";
import { useRowsStore } from "@schoolboxVue/components/grid/lazyGrid/store/lgRowsStore";
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
const { maxScrollableHeight } = useMaxScrollableHeight(scrollableEl, minWidth, [
    document.getElementById("filter_panel"),
]);
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
            <lazy-grid
                :header="headerStore.header"
                :rows="rowsStore.payload.data"
                :is-loading="rowsStore.isMoreLoading"
                :sort-by="rowsStore.sortBy"
                :order="rowsStore.order"
                @sort="rowsStore.onSort"
            >
                <template #User="{ row, header: columnHeader }">
                    <lg-user-cell :user="row[columnHeader.key] as User" />
                </template>
                <template #DateTime="{ row, header: columnHeader }">
                    <lg-date-cell :value="row[columnHeader.key] as string" />
                </template>
            </lazy-grid>
            <loading-table v-if="rowsStore.isMoreLoading" :rows="5" row-height="3rem" />
        </div>
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
