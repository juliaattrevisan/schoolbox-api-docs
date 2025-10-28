<script setup lang="ts">
/**
 * Manage Differentiation Profiles page
 */

import type { User } from "@schoolboxLibrary/types/User";
import type { MDPHeader, Header, AdjustmentType, Profile } from "../js/types/manageDifferentiationProfilesTypes";
import { HeaderType } from "../js/types/manageDifferentiationProfilesTypes";
import MdpGrid from "./grid/mdp-grid.vue";
import MdpGridCellStudent from "./grid/mdp-grid-cell-student.vue";
import MdpGridCellAdjustment from "./grid/mdp-grid-cell-adjustment.vue";

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
    <mdp-grid :header="header" :rows="rows">
        <template #User="{ row, header: columnHeader }">
            <mdp-grid-cell-student :user="row[columnHeader.key] as User" />
        </template>
        <template #Adjustment="{ row, header: columnHeader }">
            <mdp-grid-cell-adjustment
                :adjustment="row[columnHeader.key] as AdjustmentType"
                :options="columnHeader.key === 'adjustment_category' ? adjustmentCategories : adjustmentLevels"
            />
        </template>
        <template #Text="{ row, header: columnHeader }">
            <div class="text-cell">
                <!-- Custom text display -->
                {{ row[columnHeader.key] || "" }}
            </div>
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
