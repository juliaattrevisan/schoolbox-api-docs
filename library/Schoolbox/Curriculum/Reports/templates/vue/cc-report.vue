<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */

import { mockReportData } from "./mockReportData";

const data = window.Vue.ref(mockReportData.data);

function getUnitUsageCounts(usageCounts) {
    return usageCounts.unit + usageCounts.activity + usageCounts.lesson + usageCounts.rubric;
}

function getCourseUsageCounts(course) {
    let count = 0;
    console.warn(course);
    course.units.forEach((unit) => {
        count += getUnitUsageCounts(unit._usageCounts);
    });
    return count;
}
</script>

<template>
    <div>
        <table>
            <thead>
                <tr>
                    <th rowspan="2">Curriculum</th>
                    <!-- colspan will be the number of units plus total -->
                    <th v-for="course in data.courses" :key="course.id" :colspan="course.units.length + 1">
                        {{ course.name }}
                    </th>
                </tr>
                <tr v-for="course in data.courses" :key="course.id">
                    <th v-for="unit in course.units" :key="unit.id">{{ unit.name }}</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="node in data.nodes" :key="node.id">
                    <th scope="row">{{ node.name }}</th>
                    <template v-for="course in node.usage.courses" :key="course.id">
                        <td v-for="unit in course.units" :key="unit.id">{{ getUnitUsageCounts(unit._usageCounts) }}</td>
                        <td>{{ getCourseUsageCounts(course) }}</td>
                    </template>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped></style>
