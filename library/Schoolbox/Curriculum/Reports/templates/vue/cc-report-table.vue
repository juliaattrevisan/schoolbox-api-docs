<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */

import { mockReportData } from "./mockReportData";
import type { CurriculumCoveragePayload } from "../js/types/curriculumCoverageTypes";
import CcReportTableNode from "./cc-report-table-node.vue";
import { useStore } from "./store/curriculumCoverageStore";

const store = useStore();
const data = window.Vue.ref((mockReportData as CurriculumCoveragePayload).data);
/**
 * @todo - Update to use the actual data from the API
 */
// store.courses = data.value.courses; // Set the courses in the store (temporary while mock data is used)
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
                    v-for="(course, index) in store.courses"
                    :key="course.id"
                    :colspan="`${course.units && course.units.length ? course.units.length + 2 : 0} `"
                    :class="`${index % 2 ? 'u-alt-bkg' : ''}`"
                >
                    {{ course.name }}
                </th>
            </tr>
            <tr>
                <template v-for="(course, index) in store.courses" :key="course.id">
                    <td :class="`${index % 2 ? 'u-alt-bkg' : ''}`"></td>
                    <th v-for="unit in course.units" :key="unit.id" :class="`${index % 2 ? 'u-alt-bkg' : ''}`">
                        {{ unit.name }}
                    </th>
                    <th :class="`${index % 2 ? 'u-alt-bkg' : ''}`">Total</th>
                </template>
            </tr>
        </thead>
        <tbody>
            <cc-report-table-node v-for="node in store.nodes" :key="node.id" :node="node" :depth="0" />
        </tbody>
    </table>
</template>

<style scoped></style>
