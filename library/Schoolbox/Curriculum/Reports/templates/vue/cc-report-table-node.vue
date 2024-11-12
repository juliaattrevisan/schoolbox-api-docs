<script setup lang="ts">
/**
 * A single node row in the curriculum coverage report.
 */

import { useTemplateRef } from "vue";
import { gsap } from "gsap/all";
import { useNodeTitle } from "@schoolboxCurriculum/vue/composables/useNodeTitle";
import type { Course } from "@schoolboxLibrary/types/Course";
import type { Unit } from "@schoolboxLibrary/types/Unit";
import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";
import { useReportStore } from "./store/ccReportStore";
import { useFiltersStore } from "./store/ccFiltersStore";
import { ReportOptionValues } from "../js/types/curriculumCoverageTypes";

interface Props {
    node: CurriculumCoverageNode;
    depth: number; // The depth of the node in the tree
}
const props = defineProps<Props>();

const reportStore = useReportStore();
const filtersStore = useFiltersStore();
const { title } = useNodeTitle(props.node);
const paddingStart = window.Vue.ref(`calc(${props.depth * 0.5}rem + 0.5rem)`); // to gradually add more paddingStart to indicate node level depth
const hasChildren = window.Vue.ref(props.node.children && props.node.children.length > 0);

// #region expand/collapse

const isExpanded = window.Vue.ref(props.depth < 1); // to hide any grandchild and below nodes by default
const expandTitle = window.Vue.computed(() => (isExpanded.value ? "Collapse" : "Expand"));

/**
 * To toggle the expanded state of the node
 */
function onExpandClicked(): void {
    isExpanded.value = !isExpanded.value;
}

// #endregion

// #region usage counts

/**
 * Get the total usage count for a unit, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param $course - the course to get the usage count for
 * @param $unit - the unit to get the usage count for
 * @returns the total unit usage count
 */
function getUnitUsageCount(node: CurriculumCoverageNode, $course: Course, $unit: Unit): number {
    if (node.usage === undefined) return 0;
    const course = node.usage.courses.find((c) => c.id === $course.id);
    if (course === undefined) return 0;
    const unit = course.units.find((u) => u.id === $unit.id);
    if (unit === undefined) return 0;
    const { usage } = unit;
    if (usage === undefined) return 0;
    const onNodeCount = filtersStore.selectedReportOptions.reduce((acc, option) => {
        // always skip course and unit options as they can't be assigned within a unit
        if (option === ReportOptionValues.COURSE) return acc;
        if (usage[option] === undefined) return acc;
        return acc + usage[option].count;
    }, 0);
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (node.children && node.children.length > 0 && !isExpanded.value) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getUnitUsageCount(child, $course, $unit);
        }, 0);
        return onNodeCount + onChildrenCount;
    }
    return onNodeCount;
}

/**
 * Get the total usage count for a course, which is the
 * total usage count linked to the course, with the selected report options considered
 * @param node - the node to get the usage count for
 * @param $course - the course to get the usage count for
 * @returns the total course usage count
 */
function getCourseUsageCount(node: CurriculumCoverageNode, $course: Course): number {
    if (node.usage === undefined) return 0;
    const course = node.usage.courses.find((c) => c.id === $course.id);
    if (course === undefined) return 0;
    const { usage } = course;
    if (usage === undefined) return 0;
    const onNodeCount = filtersStore.selectedReportOptions.reduce((acc, option) => {
        // always skip unit, learning activity and lesson plan options as they can't be assigned within a course
        if (
            option === ReportOptionValues.UNIT ||
            option === ReportOptionValues.LEARNING_ACTIVITY ||
            option === ReportOptionValues.LEARNING_ACTIVITY_LESSON_PLAN ||
            option === ReportOptionValues.RUBRIC ||
            option === ReportOptionValues.IN_USE_RUBRIC
        )
            return acc;
        if (usage[option] === undefined) return acc;
        return acc + usage[option].count;
    }, 0);
    /**
     * When the node has children and is not expanded, we want to show the usage count inclusive of children
     * to show how many usages are linked to the node and its children
     */
    if (node.children && node.children.length > 0 && !isExpanded.value) {
        const onChildrenCount = node.children.reduce((acc, child) => {
            return acc + getCourseUsageCount(child, $course);
        }, 0);
        return onNodeCount + onChildrenCount;
    }
    return onNodeCount;
}

/**
 * Get the total node usage count for a course,
 * which is the total usage count linked to the course, and all units within the course
 * @param node - the node to get the usage count for
 * @param $course - the course to get the usage count for
 * @returns the total node usage count for all selected course(s)
 */
function getTotalUsageCount(node: CurriculumCoverageNode, $course: Course): number {
    if (node.usage === undefined) return 0;
    const course = node.usage.courses.find((c) => c.id === $course.id);
    if (course === undefined || course.units === undefined) return 0;
    const courseUsageCount = getCourseUsageCount(node, $course);
    const unitUsageCount = course.units.reduce((acc, unit) => {
        return acc + getUnitUsageCount(node, course, unit);
    }, 0);
    return courseUsageCount + unitUsageCount;
}

/**
 * Get the total node usage count for a node,
 * which is the total usage count for all courses
 * @param node - the node to get the usage count for
 * @returns the total node usage count
 */
function getEntireUsageCount(node: CurriculumCoverageNode): number {
    if (node.usage === undefined) return 0;
    return node.usage.courses.reduce((acc, course) => {
        return acc + getTotalUsageCount(node, course);
    }, 0);
}

/**
 * Whether the node contains any course usage counts that are 0
 * for the currently selected report options
 * @returns - whether the node has no course usages
 */
const hasNoCourseUsages = window.Vue.computed(() => {
    if (props.node.usage === undefined) return true;
    // loop through every course, checking if any have a usage count of 0
    return !reportStore.courses.every((course) => {
        return getCourseUsageCount(props.node, course) !== 0;
    });
});

/**
 * Whether the node contains any unit usage counts that are 0
 * for the currently selected report options
 * @returns - whether the node has no unit usages
 */
const hasNoUnitUsages = window.Vue.computed(() => {
    if (props.node.usage === undefined) return true;
    // loop through every unit in every course, checking if any have a usage count of 0
    return !reportStore.courses.every((course) => {
        return course.units?.every((unit) => {
            return getUnitUsageCount(props.node, course, unit) !== 0;
        });
    });
});

/**
 * If has a unit with no usage, and only show gaps is selected, show the node
 * @returns - whether to show the node
 */
const showNode = window.Vue.computed(() => {
    // always show the node when onlyShowGaps is false
    if (!filtersStore.onlyShowGaps) {
        return true;
    }
    // otherwise, only show the node if it has no usages
    return (hasNoCourseUsages.value || hasNoUnitUsages.value) && filtersStore.onlyShowGaps;
});

// #endregion

const nodeEl = useTemplateRef<HTMLElement | null>("nodeEl");

/**
 * Fade in when shown to help provide context
 */
window.Vue.onMounted(() => {
    // expand the node if it has no children
    if (props.depth > 2 && showNode.value) {
        gsap.from(nodeEl.value, { opacity: 0, duration: 0.3 });
    }
});
</script>

<template>
    <tr v-if="showNode" ref="nodeEl">
        <td
            class="node__name l-flex l-flex--align-center l-gap-s f-no-margins"
            :style="{ paddingInlineStart: paddingStart }"
        >
            <button
                v-if="props.depth !== 0 && hasChildren"
                :class="`expand-button expander l-flex l-flex--center ${isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'}`"
                :aria-label="expandTitle"
                :title="expandTitle"
                @click="onExpandClicked"
            ></button
            >{{ title }}
        </td>
        <td :class="``">
            {{ getEntireUsageCount(props.node) }}
        </td>
        <!-- loop through each course/unit, retrieving the node usage or returning 0 for undefined usages -->
        <template v-for="course in reportStore.courses" :key="course.id">
            <td class="course-column">
                {{ getCourseUsageCount(props.node, course) }}
            </td>
            <td v-for="unit in course.units" :key="unit.id" :class="``">
                {{ getUnitUsageCount(props.node, course, unit) }}
            </td>
            <td v-if="reportStore.hasMultipleCourses" :class="``">
                {{ getTotalUsageCount(props.node, course) }}
            </td>
        </template>
    </tr>
    <template v-if="hasChildren && isExpanded">
        <cc-report-table-node
            v-for="child in props.node.children"
            :key="child.id"
            :node="child"
            :depth="props.depth + 1"
        />
    </template>
</template>

<style scoped>
td {
    border-bottom: 1px solid black;
    border-right: 1px solid black;
}

.course-column {
    border-left: 1px solid black;
}

.expand-button {
    background: none;
}
</style>
