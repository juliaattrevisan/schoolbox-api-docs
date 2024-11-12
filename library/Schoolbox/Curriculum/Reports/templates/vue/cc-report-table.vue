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
        <table>
            <thead>
                <tr>
                    <th rowspan="2" class="curriculum l-valign--bottom">Curriculum</th>
                    <th rowspan="2" class="l-valign--bottom u-key-bkg">Total</th>
                    <!-- colspan will be the number of units plus total -->
                    <th
                        v-for="(course, index) in reportStore.courses"
                        :key="course.id"
                        :colspan="`${course.units && course.units.length ? course.units.length + 2 : 0} `"
                        :class="`${index % 2 ? 'u-alt-bkg' : ''}`"
                    >
                        {{ course.name }}
                    </th>
                </tr>
                <tr>
                    <template v-for="(course, index) in reportStore.courses" :key="course.id">
                        <td :class="`${index % 2 ? 'u-alt-bkg' : ''}`"></td>
                        <th v-for="unit in course.units" :key="unit.id" :class="`${index % 2 ? 'u-alt-bkg' : ''}`">
                            {{ unit.name }}
                        </th>
                        <th v-if="reportStore.hasMultipleCourses" :class="`${index % 2 ? 'u-alt-bkg' : ''}`">Total</th>
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
.curriculum {
    width: 500px; /* fixed width for the curriculum column to prevent shifts on expand/collapse */
}
</style>
