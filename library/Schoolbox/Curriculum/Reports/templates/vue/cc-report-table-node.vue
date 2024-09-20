<script setup lang="ts">
/**
 * A single node row in the curriculum coverage report.
 */

import { useNodeTitle } from "@schoolboxCurriculum/vue/composables/useNodeTitle";
import type {
    CourseWithCurriculumNodeUsage,
    UnitWithCurriculumNodeUsage,
    CurriculumCoverageNode,
} from "../js/types/curriculumCoverageTypes";
import { useStore } from "./store/curriculumCoverageStore";

interface Props {
    node: CurriculumCoverageNode;
    depth: number; // The depth of the node in the tree
}
const props = defineProps<Props>();

const store = useStore();
const { title } = useNodeTitle(props.node);

/**
 * to gradually add more paddingStart to indicate node level depth
 * @returns - the paddingStart value
 */
const paddingStart = window.Vue.computed(() => `calc(${props.depth * 0.75}rem + 0.625rem)`);

/**
 * Get the total usage count for a unit
 * @param usageCounts - the usage counts for the unit
 * @returns the total unit usage count
 */
function getUnitUsageCount(usageCounts: UnitWithCurriculumNodeUsage["_usageCounts"]): number {
    return store.selectedReportOptionValues.reduce((acc, option) => {
        return acc + usageCounts[option];
    }, 0);
}

/**
 * Get the total node usage count for a course(s)
 * @param course - the course to get the usage count for
 * @returns the total node usage count for all selected course(s)
 */
function getCourseUsageCount(course: CourseWithCurriculumNodeUsage): number {
    if (course.units === undefined) return 0;
    return course.units.reduce((acc, unit) => {
        return acc + getUnitUsageCount(unit._usageCounts);
    }, 0);
}

/**
 * Get the total node usage count for a node
 * @returns the total node usage count
 */
const nodeUsageCount = window.Vue.computed(() => {
    return props.node.usage.courses.reduce((acc, course) => {
        return acc + getCourseUsageCount(course);
    }, 0);
});

/**
 * Whether the node contains any unit usage counts that are 0
 * for the currently selected report options
 * @returns - whether the node has no usages
 */
const hasNoUsages = window.Vue.computed(() => {
    return !props.node.usage.courses.every((course) => {
        return course.units.every((unit) => {
            return getUnitUsageCount(unit._usageCounts) !== 0;
        });
    });
});

/**
 * If has a unit with no usage, and only show gaps is selected, show the node
 * @returns - whether to show the node
 */
const showNode = window.Vue.computed(() => {
    // always show the node when onlyShowGaps is false
    if (!store.onlyShowGaps) {
        return true;
    }
    // otherwise, only show the node if it has no usages
    return hasNoUsages.value && store.onlyShowGaps;
});
</script>

<template>
    <tr v-if="showNode">
        <th scope="row" class="node__name">{{ title }}</th>
        <td :class="`u-alt-bkg ${nodeUsageCount === 0 ? 'u-highlight' : ''}`">
            {{ nodeUsageCount }}
        </td>
        <template v-for="(course, index) in props.node.usage.courses" :key="course.id">
            <td
                v-for="unit in course.units"
                :key="unit.id"
                :class="`${index % 2 ? 'u-alt-bkg' : ''} ${getUnitUsageCount(unit._usageCounts) === 0 ? 'u-highlight' : ''}`"
            >
                {{ getUnitUsageCount(unit._usageCounts) }}
            </td>
            <td :class="`${index % 2 ? 'u-alt-bkg' : ''} ${getCourseUsageCount(course) === 0 ? 'u-highlight' : ''}`">
                {{ getCourseUsageCount(course) }}
            </td>
        </template>
    </tr>
    <template v-if="props.node.children && props.node.children.length > 0">
        <cc-report-table-node
            v-for="child in props.node.children"
            :key="child.id"
            :node="child"
            :depth="props.depth + 1"
        />
    </template>
</template>

<style scoped>
.node__name {
    padding-inline-start: v-bind(paddingStart);
}
</style>
