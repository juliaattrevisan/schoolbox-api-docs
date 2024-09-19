<script setup lang="ts">
/**
 * A single node row in the curriculum coverage report.
 */

import { CourseWithCurriculumNodeUsage, UnitWithCurriculumNodeUsage, CurriculumCoverageNode } from "./tempTypes";

interface Props {
    node: CurriculumCoverageNode;
    depth: number; // The depth of the node in the tree
}
const props = defineProps<Props>();

/**
 * to gradually add more paddingStart to indicate node level depth
 * @returns { number }
 */
const paddingStart = window.Vue.computed(() => `calc(${props.depth * 0.75}rem + 0.625rem)`);

/**
 * Get the total usage count for a unit
 * @param usageCounts - the usage counts for the unit
 * @returns the total unit usage count
 */
function getUnitUsageCount(usageCounts: UnitWithCurriculumNodeUsage["_usageCounts"]): number {
    return usageCounts.unit + usageCounts.activity + usageCounts.lesson + usageCounts.rubric;
}

/**
 * Get the total node usage count for a course(s)
 * @param course - the course to get the usage count for
 * @returns the total node usage count for all selected course(s)
 */
function getCourseUsageCount(course: CourseWithCurriculumNodeUsage): number {
    let count = 0;
    if (course.units) {
        course.units.forEach((unit) => {
            count += getUnitUsageCount(unit._usageCounts);
        });
    }
    return count;
}

/**
 * Get the total node usage count for a node
 * @param node - the node to get the usage count for
 * @returns the total node usage count
 */
function getNodeUsageCount(node: CurriculumCoverageNode): number {
    let count = 0;
    node.usage.courses.forEach((course) => {
        count += getCourseUsageCount(course);
    });
    return count;
}
</script>

<template>
    <tr>
        <th scope="row" class="node__name">{{ props.node.name }}</th>
        <td>{{ getNodeUsageCount(props.node) }}</td>
        <template v-for="course in props.node.usage.courses" :key="course.id">
            <td v-for="unit in course.units" :key="unit.id">
                {{ getUnitUsageCount(unit._usageCounts) }}
            </td>
            <td>{{ getCourseUsageCount(course) }}</td>
        </template>
    </tr>
    <template v-if="props.node.children && props.node.children.length > 0">
        <cc-report-node v-for="child in props.node.children" :key="child.id" :node="child" :depth="props.depth + 1" />
    </template>
</template>

<style scoped>
.node__name {
    padding-inline-start: v-bind(paddingStart);
}
</style>
