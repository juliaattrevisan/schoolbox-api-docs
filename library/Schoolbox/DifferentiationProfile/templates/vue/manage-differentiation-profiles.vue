<script setup lang="ts">
/**
 * Manage Differentiation Profiles page
 */

import locale from "@js/terminology/rosettaStone";
import type { UserCC as User } from "@schoolboxLibrary/types/User";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import type { MDPHeader, Header, AdjustmentType, Profile, Text } from "../js/types/manageDifferentiationProfilesTypes";
import { HeaderType } from "../js/types/manageDifferentiationProfilesTypes";
import MdpGrid from "./grid/mdp-grid.vue";
import MdpGridCellStudent from "./grid/mdp-grid-cell-student.vue";
import MdpGridCellAdjustment from "./grid/mdp-grid-cell-adjustment.vue";
import MdpGridCellText from "./grid/mdp-grid-cell-text.vue";

const props = defineProps<{
    header: MDPHeader[];
    adjustmentCategories: AdjustmentType[];
    adjustmentLevels: AdjustmentType[];
    profiles: Profile[];
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

const rows = window.Vue.ref<Profile[]>(props.profiles);

// #endregion grid data
</script>

<template>
    <empty-state v-if="rows.length === 0" icon="icon-learning-support">
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
            <mdp-grid-cell-text :text="row[columnHeader.key] as Text" />
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
