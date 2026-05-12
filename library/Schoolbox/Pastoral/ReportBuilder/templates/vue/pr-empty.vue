<script setup lang="ts">
/**
 * To display an empty state for the Pastoral Report Builder
 * when no records are available.
 */

import type { Routes } from "../js/types/pastoralReportTypes";

const localePastoral = window.Vue.inject<string>("localePastoral");
const routes = window.Vue.inject<Routes>("pastoralRoutes");
</script>

<template>
    <div class="empty-state-flex-centered f-no-margins">
        <sbx-icon name="pastoral" size="2.5rem"></sbx-icon>
        <p>No {{ localePastoral }} records matched your report criteria.</p>
        <a :href="routes?.getIndex"> Select Another Report </a>
    </div>
</template>

<style scoped></style>
