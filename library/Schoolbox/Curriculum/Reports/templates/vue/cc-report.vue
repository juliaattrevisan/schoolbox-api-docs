<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */

import { mockReportData } from "./mockReportData";

const data = window.Vue.ref(mockReportData.data);

function getUnitUsageCount(usageCounts) {
    return usageCounts.unit + usageCounts.activity + usageCounts.lesson + usageCounts.rubric;
}

function getCourseUsageCount(course) {
    let count = 0;
    course.units.forEach((unit) => {
        count += getUnitUsageCount(unit._usageCounts);
    });
    return count;
}

function getNodeUsageCount(node) {
    let count = 0;
    node.usage.courses.forEach((course) => {
        count += getCourseUsageCount(course);
    });
    return count;
}
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
                <tr v-for="node in data.nodes" :key="node.id">
                    <th scope="row">{{ node.name }}</th>
                    <td>{{ getNodeUsageCount(node) }}</td>
                    <template v-for="course in node.usage.courses" :key="course.id">
                        <td v-for="unit in course.units" :key="unit.id">{{ getUnitUsageCount(unit._usageCounts) }}</td>
                        <td>{{ getCourseUsageCount(course) }}</td>
                    </template>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.l-valign--bottom {
    vertical-align: bottom;
}
</style>
