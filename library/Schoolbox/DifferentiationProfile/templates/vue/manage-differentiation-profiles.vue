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
import DummyInlineEditor from "@schoolboxVue/components/ckeditor/dummy-inline-editor.vue";
import PastoralRecordsModal from "@schoolboxLibrary/Pastoral/vue/pastoral-records-modal.vue";
import type { Routes, Config } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import type {
    MDPHeader,
    AdjustmentType,
    ProfileAdjustmentKeys,
    ProfileTextKeys,
    Payload,
    Profile,
} from "../js/types/manageDifferentiationProfilesTypes";
import MdpGrid from "./grid/mdp-grid.vue";
import MdpGridCellStudent from "./grid/mdp-grid-cell-student.vue";
import MdpGridCellAdjustment from "./grid/mdp-grid-cell-adjustment.vue";
import MdpGridCellText from "./grid/mdp-grid-cell-text.vue";
import { useMdpHeaderStore } from "./store/mdpHeaderStore";
import { useMdpRowsStore } from "./store/mdpRowsStore";

const props = defineProps<{
    header: MDPHeader[];
    adjustmentCategories: AdjustmentType[];
    adjustmentLevels: AdjustmentType[];
    route: string;
    pastoralConfig: Config;
    pastoralRoutes: Routes;
}>();

window.Vue.provide("pastoralConfig", props.pastoralConfig);
window.Vue.provide("pastoralRoutes", props.pastoralRoutes);

const headerStore = useMdpHeaderStore();
headerStore.init(props.header);
const rowsStore = useMdpRowsStore();
rowsStore.init(props.route);

// #region linked records selected

const linkedRecordsUrl = window.Vue.ref<string>("");
const linkedRecordsTitle = window.Vue.ref<string>("");
const linkedRecordsMeta = window.Vue.ref<string>("");

/**
 * When linked records is selected for a profile
 * @param profile - the profile to view linked records for
 */
function onLinkedRecordsSelected(profile: Profile): void {
    if (profile._routes?.linkedRecords) {
        linkedRecordsTitle.value = `${profile.student.fullName} - Linked Records`;
        linkedRecordsUrl.value = profile._routes.linkedRecords;
    }
}

// #endregion linked records selected
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
        <mdp-grid
            :header="headerStore.header"
            :rows="rowsStore.payload.data.profiles"
            :is-loading="rowsStore.isMoreLoading"
        >
            <template #User="{ row, header: columnHeader }">
                <mdp-grid-cell-student
                    :user="row[columnHeader.key] as User"
                    :profile="row as Profile"
                    @linked-records-select="onLinkedRecordsSelected"
                />
            </template>
            <template #Adjustment="{ row, header: columnHeader }">
                <mdp-grid-cell-adjustment
                    :profile="row as Profile"
                    :header-key="columnHeader.key as ProfileAdjustmentKeys"
                    :adjustments="row[columnHeader.key] as number[]"
                    :options="
                        columnHeader.key === 'adjustment_category' ? props.adjustmentCategories : props.adjustmentLevels
                    "
                />
            </template>
            <template #Text="{ row, header: columnHeader }">
                <mdp-grid-cell-text
                    :profile="row as Profile"
                    :header-key="columnHeader.key as ProfileTextKeys"
                    :text="row[columnHeader.key] as string | null"
                />
            </template>
            <template #Date="{ row, header: columnHeader }">
                <div class="l-pad-s">
                    <!-- Custom date display -->
                    {{ row[columnHeader.key] ? new Date(row[columnHeader.key] as string).toLocaleDateString() : "" }}
                </div>
            </template>
        </mdp-grid>
        <loading-table v-if="rowsStore.isMoreLoading" :rows="5" row-height="3rem" />
        <cursor-pagination
            :url="rowsStore.url"
            :metadata="(rowsStore.payload as Payload).metadata"
            :limit="rowsStore.limit"
            :is-loading="rowsStore.isNewLoading || rowsStore.isMoreLoading"
            @previous="rowsStore.onPaginate"
            @next="rowsStore.onPaginate"
        />
    </template>
    <pastoral-records-modal v-model:url="linkedRecordsUrl" :title="linkedRecordsTitle" :meta="linkedRecordsMeta" />
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
