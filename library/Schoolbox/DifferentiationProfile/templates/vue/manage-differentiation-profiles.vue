<script setup lang="ts">
/**
 * Manage Differentiation Profiles page
 */

import type { MDPHeader, Header, AdjustmentType, Profile } from "../js/types/manageDifferentiationProfilesTypes";
import MdpGrid from "./mdp-grid.vue";

const props = defineProps<{
    header: MDPHeader[];
    adjustmentCategories: AdjustmentType[];
    adjustmentLevels: AdjustmentType[];
    profiles: Profile[];
}>();

const header = window.Vue.ref<Header[]>([
    { id: 0, title: "Student", width: 300, isFixed: true, isSortable: true },
    ...props.header.map((h) => ({
        id: h.id,
        title: h.terminology,
        width: 400,
        isFixed: false,
        isSortable: false,
    })),
    {
        id: Math.max(0, ...props.header.map((h) => h.id)) + 1,
        title: "Last updated",
        width: 200,
        isFixed: false,
        isSortable: true,
    },
]);
</script>

<template>
    <mdp-grid :header="header" />
</template>

<style scoped></style>
