<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */

import CcReportHeader from "./cc-report-header.vue";
import CcReportTable from "./cc-report-table.vue";
</script>

<template>
    <div class="c-island l-pad-m">
        <div class="l-flex l-flex-col l-gap-s">
            <cc-report-header />
            <cc-report-table />
        </div>
    </div>
</template>

<style scoped></style>
