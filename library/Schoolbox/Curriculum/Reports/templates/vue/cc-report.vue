<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import CcReportFilters from "./cc-report-filters.vue";
import CcReportTable from "./cc-report-table.vue";

import { useReportStore } from "./store/ccReportStore";

const reportStore = useReportStore();
</script>

<template>
    <div v-if="reportStore.hasNotReported" class="empty-state-flex-centered">
        <i class="icon-report"></i>
        To generate a report, first select at least one Course and Curriculum.
    </div>
    <loading-table v-else-if="reportStore.isLoading" />
    <div v-else-if="reportStore.hasReport" class="c-island l-pad-m">
        <div class="l-flex l-flex-col l-gap-s">
            <cc-report-filters />
            <cc-report-table />
        </div>
    </div>
</template>

<style scoped></style>
