<script setup lang="ts">
/**
 * To display an empty state for the Pastoral Report Builder
 * when no records are available.
 */

import { useStore } from "./store/pastoralReportStore";

const store = useStore();
</script>

<template>
    <div class="empty-state-flex-centered f-no-margins">
        <i class="icon-pastoral"></i>
        <p>No {{ store.localePastoral }} records matched your report criteria.</p>
        <a :href="store.routes.getIndex"> Select Another Report </a>
    </div>
</template>

<style scoped></style>
