<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */

import { mockReportData } from "./mockReportData";
import { CurriculumCoveragePayload } from "./tempTypes";
import CcReportNode from "./cc-report-node.vue";

const data = window.Vue.ref((mockReportData as CurriculumCoveragePayload).data);
</script>

<template>
    <div>
        <table>
            <thead>
                <tr>
                    <th rowspan="2" class="l-valign--bottom">Curriculum</th>
                    <th rowspan="2" class="l-valign--bottom">Total</th>
                    <!-- colspan will be the number of units plus total -->
                    <th v-for="course in data.courses" :key="course.id" :colspan="course.units.length + 1">
                        {{ course.name }}
                    </th>
                </tr>
                <tr>
                    <template v-for="course in data.courses" :key="course.id">
                        <th v-for="unit in course.units" :key="unit.id">{{ unit.name }}</th>
                        <th>Total</th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <cc-report-node v-for="node in data.nodes" :key="node.id" :node="node" />
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.l-valign--bottom {
    vertical-align: bottom;
}
</style>
