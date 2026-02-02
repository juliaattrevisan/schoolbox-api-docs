<script setup lang="ts">
/**
 * Manage Customisation Records page
 */

import type { UserCC as User } from "@schoolboxLibrary/types/User";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import LazyGrid from "@schoolboxVue/components/grid/lazyGrid/lazy-grid.vue";
import { useMaxScrollableHeight } from "@schoolboxVue/composables/content/useMaxScrollableHeight";
import type { Routes as PastoralRoutes, Config } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import type {
    Routes,
    CRHeader,
    AdjustmentType,
    ProfileAdjustmentKeys,
    ProfileTextKeys,
    Payload,
    Profile,
} from "../js/types/manageDifferentiationProfilesTypes";
import CrGridCellStudent from "./grid/cell/cr-grid-cell-student.vue";
import { useHeaderStore } from "./store/crHeaderStore";
import { useRowsStore } from "./store/crRowsStore";
const props = defineProps<{
    header: CRHeader[];
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
                        :adjustments="row[columnHeader.key] as number[]"
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
