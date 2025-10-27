<script setup lang="ts">
/**
 * Manage Differentiation Profiles page
 */

import type { Header, AdjustmentType, Profile } from "../js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    header: Header[];
    adjustmentCategories: AdjustmentType[];
    adjustmentLevels: AdjustmentType[];
    profiles: Profile[];
}>();
</script>

<template>
    <div></div>
</template>

<style scoped></style>
