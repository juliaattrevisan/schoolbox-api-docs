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
    <div></div>
    <table>
        <thead>
            <tr>
                <th rowspan="2" class="l-valign--bottom">Curriculum</th>
                <th rowspan="2" class="l-valign--bottom u-alt-bkg">Total</th>
                <!-- colspan will be the number of units plus total -->
                <th
                    v-for="(course, index) in data.courses"
                    :key="course.id"
                    :colspan="course.units.length + 1"
                    :class="`${index % 2 ? 'u-alt-bkg' : ''}`"
                >
                    {{ course.name }}
                </th>
            </tr>
            <tr>
                <template v-for="(course, index) in data.courses" :key="course.id">
                    <th v-for="unit in course.units" :key="unit.id" :class="`${index % 2 ? 'u-alt-bkg' : ''}`">
                        {{ unit.name }}
                    </th>
                    <th :class="`${index % 2 ? 'u-alt-bkg' : ''}`">Total</th>
                </template>
            </tr>
        </thead>
        <tbody>
            <cc-report-node v-for="node in data.nodes" :key="node.id" :node="node" :depth="0" />
        </tbody>
    </table>
</template>

<style scoped></style>
