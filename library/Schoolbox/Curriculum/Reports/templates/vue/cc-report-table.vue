<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */

import CcReportTableNode from "./cc-report-table-node.vue";
import { useReportStore } from "./store/ccReportStore";

const reportStore = useReportStore();
</script>

<template>
    <div class="scrollable">
        <table class="coverage-report">
            <thead>
                <tr>
                    <th rowspan="2" class="curriculum l-valign--bottom">Curriculum</th>
                    <th rowspan="2" class="l-valign--bottom">Total</th>
                    <template v-for="course in reportStore.courses" :key="course.id">
                        <td class="course-column"><b>Course:</b> {{ course.name }}</td>
                        <td v-for="unit in course.units" :key="unit.id" :class="``"><b>Unit:</b> {{ unit.name }}</td>
                        <td v-if="reportStore.hasMultipleCourses" :class="``"><b>Total</b></td>
                    </template>
                </tr>
            </thead>
            <tbody>
                <cc-report-table-node v-for="node in reportStore.nodes" :key="node.id" :node="node" :depth="0" />
            </tbody>
        </table>
    </div>
</template>

<style scoped>
table {
    border-left: 1px solid black;
    border-top: 1px solid black;
}

table thead tr td {
    font-weight: normal;
}

td,
th {
    border-bottom: 1px solid black;
    border-right: 1px solid black;
}

.course-column {
    border-left: 1px solid black;
}

.curriculum {
    width: 500px; /* fixed width for the curriculum column to prevent shifts on expand/collapse */
}
</style>
