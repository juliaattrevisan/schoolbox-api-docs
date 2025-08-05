<script setup lang="ts">
/**
 * To display an empty state for the Pastoral Report Builder
 * when no records are available.
 */

import type { Routes } from "../js/types/pastoralReportTypes";

const props = defineProps<{
    routes: Routes;
    localePastoral: string;
}>();
</script>

<template>
    <div class="empty-state-flex-centered f-no-margins">
        <i class="icon-pastoral"></i>
        <p>No {{ props.localePastoral }} records matched your report criteria.</p>
        <a :href="props.routes.getIndex"> Select Another Report </a>
    </div>
</template>

<style scoped></style>
