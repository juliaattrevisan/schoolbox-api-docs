<script setup lang="ts">
/**
 * Manage Differentiation Profiles page
 */

import type { MDPHeader, Header, AdjustmentType, Profile } from "../js/types/manageDifferentiationProfilesTypes";
import { HeaderType } from "../js/types/manageDifferentiationProfilesTypes";
import MdpGrid from "./mdp-grid.vue";

const props = defineProps<{
    header: MDPHeader[];
    adjustmentCategories: AdjustmentType[];
    adjustmentLevels: AdjustmentType[];
    profiles: Profile[];
}>();

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
</script>

<template>
    <mdp-grid :header="header" :rows="rows" />
</template>

<style scoped></style>
