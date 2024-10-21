<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import CcReportHeader from "./cc-report-header.vue";
import CcReportTable from "./cc-report-table.vue";

import { useStore } from "./store/curriculumCoverageStore";

const store = useStore();
</script>

<template>
    <div v-if="store.hasReport" class="c-island l-pad-m">
        <div class="l-flex l-flex-col l-gap-s">
            <cc-report-header />
            <cc-report-table />
        </div>
    </div>
    <loading-table v-else-if="store.isLoading" />
    <div v-else class="empty-state-flex-centered">
        <i class="icon-report"></i>
        To generate a report, first select at least one Course and Curriculum.
    </div>
</template>

<style scoped></style>
