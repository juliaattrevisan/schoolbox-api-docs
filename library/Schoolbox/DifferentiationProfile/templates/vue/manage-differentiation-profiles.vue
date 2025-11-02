<script setup lang="ts">
/**
 * Manage Differentiation Profiles page
 */

import locale from "@js/terminology/rosettaStone";
import type { UserCC as User } from "@schoolboxLibrary/types/User";
import { listenForFilterLoad, listenForFilterLoading } from "@schoolboxLibrary/Filter/templates/ts/filterEvent";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import type {
    MDPHeader,
    Header,
    AdjustmentType,
    Profile,
    Payload,
    AdjustmentText,
} from "../js/types/manageDifferentiationProfilesTypes";
import { HeaderType } from "../js/types/manageDifferentiationProfilesTypes";
import MdpGrid from "./grid/mdp-grid.vue";
import MdpGridCellStudent from "./grid/mdp-grid-cell-student.vue";
import MdpGridCellAdjustment from "./grid/mdp-grid-cell-adjustment.vue";
import MdpGridCellText from "./grid/mdp-grid-cell-text.vue";

const props = defineProps<{
    header: MDPHeader[];
    adjustmentCategories: AdjustmentType[];
    adjustmentLevels: AdjustmentType[];
}>();

// #region grid data

const header = window.Vue.ref<Header[]>([
    { id: 0, title: "Student", width: 250, isFixed: true, isSortable: true, key: "student", type: HeaderType.USER },
    ...props.header.map((h) => ({
        id: h.id,
        key: h.name,
        type:
            h.name === "adjustment_category" || h.name === "adjustment_level" ? HeaderType.ADJUSTMENT : HeaderType.TEXT,
        title: h.terminology,
        width: 400,
        isFixed: false,
        isSortable: false,
    })),
    {
        id: Math.max(0, ...props.header.map((h) => h.id)) + 1,
        key: "updated_at",
        type: HeaderType.DATE,
        title: "Last updated",
        width: 200,
        isFixed: false,
        isSortable: true,
    },
]);

const data = window.Vue.ref<Payload | null>(null);
const rows = window.Vue.ref<Profile[]>([]);

// #endregion grid data

/**
 * When we start loading new data, we reset the data to null to show the loading state
 */
function onLoading(): void {
    data.value = null; // Reset the data to show loading state
}

/**
 * When the data has successfully loaded
 * @param $data - The profile data
 */
function onLoaded($data: Payload): void {
    data.value = $data;
    rows.value = data.value.data.profiles;
}

const filterKey = "MDP";

listenForFilterLoading(filterKey, onLoading);
listenForFilterLoad<Payload>(filterKey, (detail) => onLoaded(detail.content));
</script>

<template>
    <div v-if="data === null" class="c-island l-pad-m">
        <div class="loader"></div>
    </div>
    <empty-state v-else-if="rows.length === 0" icon="icon-learning-support">
        <p>
            No {{ locale.differentiationProfile.translate(true, true) }} exist. They will appear here once records have
            been linked.
        </p>
    </empty-state>
    <mdp-grid v-else :header="header" :rows="rows">
        <template #User="{ row, header: columnHeader }">
            <mdp-grid-cell-student :user="row[columnHeader.key] as User" />
        </template>
        <template #Adjustment="{ row, header: columnHeader }">
            <mdp-grid-cell-adjustment
                :adjustment="row[columnHeader.key] as AdjustmentType[]"
                :options="columnHeader.key === 'adjustment_category' ? adjustmentCategories : adjustmentLevels"
            />
        </template>
        <template #Text="{ row, header: columnHeader }">
            <mdp-grid-cell-text :text="row[columnHeader.key] as AdjustmentText" />
        </template>
        <template #Date="{ row, header: columnHeader }">
            <div class="date-cell">
                <!-- Custom date display -->
                {{ row[columnHeader.key] ? new Date(row[columnHeader.key] as string).toLocaleDateString() : "" }}
            </div>
        </template>
    </mdp-grid>
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
