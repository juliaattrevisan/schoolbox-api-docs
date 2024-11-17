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
import ccReportTableNodeUnit from "./cc-report-table-node-unit.vue";

interface Props {
    node: CurriculumCoverageNode;
    depth: number; // The depth of the node in the tree
}
const props = defineProps<Props>();

const emit = defineEmits<{
    titleClick: [node: CurriculumCoverageNode];
}>();

const reportStore = useReportStore();
const filtersStore = useFiltersStore();
const { title } = useNodeTitle(props.node);
const paddingStart = window.Vue.ref(`calc(${props.depth * 0.5}rem + 0.5rem)`); // to gradually add more paddingStart to indicate node level depth
const hasChildren = window.Vue.ref(Array.isArray(props.node.children) && props.node.children.length > 0);

// #region expand/collapse

const isExpanded = window.Vue.ref(props.depth < 1); // to hide any grandchild and below nodes by default
const expandTitle = window.Vue.computed(() => (isExpanded.value ? "Collapse" : "Expand"));
// don't show the expand button when the node is at the top level, has no children, or onlyShowGaps is active
const showExpandButton = window.Vue.computed(
    () => props.depth !== 0 && hasChildren.value && !filtersStore.onlyShowGaps,
);
const lastChildPadding = window.Vue.computed(() =>
    props.depth !== 0 && !hasChildren.value && !filtersStore.onlyShowGaps ? "2.75rem" : "0",
); // to add padding to the last child node to align better with the expand button

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
 * If has a unit with no usage, and only show gaps is selected, show the node
 * @returns - whether to show the node
 */
const showNode = window.Vue.computed(() => {
    // always show the node when onlyShowGaps is false
    if (!filtersStore.onlyShowGaps) {
        return true;
    }
    // don't show nodes that can't have usage assigned when only show gaps is active
    if ((filtersStore.onlyShowGaps && props.node.code === null) || props.node.code === "") {
        return false;
    }
    return getEntireUsageCount(props.node) === 0 && filtersStore.onlyShowGaps;
});

// #endregion

/**
 * Fade in when shown to help provide context
 */
const nodeEl = useTemplateRef<HTMLElement | null>("nodeEl");
window.Vue.onMounted(async () => {
    // expand the node if it has no children
    if (props.depth > 1 && showNode.value) {
        await window.Vue.nextTick();
        gsap.from(nodeEl.value, { opacity: 0, duration: 0.3 });
    }
});

/**
 * Emit to open up the node details modal
 */
function onTitleClicked(): void {
    emit("titleClick", props.node);
}

/**
 * Emit to open up the node details modal
 * @param node - the node that was selected
 */
function onChildTitleClicked(node: CurriculumCoverageNode): void {
    emit("titleClick", node);
}
</script>

<template>
    <tr v-if="showNode" ref="nodeEl">
        <td
            class="node__name l-flex l-flex--align-center l-gap-s f-no-margins"
            :style="{ paddingInlineStart: paddingStart }"
        >
            <button
                v-if="showExpandButton"
                :class="`expand-button expander l-flex l-flex--center ${isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'}`"
                :aria-label="expandTitle"
                :title="expandTitle"
                @click="onExpandClicked"
            ></button>
            <button class="title-button" :style="{ paddingLeft: lastChildPadding }" @click="onTitleClicked">
                {{ title }}
            </button>
        </td>
        <td class="u-border-left">
            <b>{{ getEntireUsageCount(props.node) }}</b>
        </td>
        <!-- loop through each course/unit, retrieving the node usage or returning 0 for undefined usages -->
        <template v-for="course in reportStore.courses" :key="course.id">
            <td class="u-border-left">
                {{ getCourseUsageCount(props.node, course) }}
            </td>
            <cc-report-table-node-unit
                v-for="unit in course.units"
                :key="unit.id"
                :course="course"
                :unit="unit"
                :node="props.node"
                :count="getUnitUsageCount(props.node, course, unit)"
                :is-expanded="isExpanded"
            />
            <td v-if="reportStore.hasMultipleCourses" :class="``">
                {{ getTotalUsageCount(props.node, course) }}
            </td>
        </template>
    </tr>
    <template v-if="hasChildren && (isExpanded || filtersStore.onlyShowGaps)">
        <cc-report-table-node
            v-for="child in props.node.children"
            :key="child.id"
            :node="child"
            :depth="props.depth + 1"
            @title-click="onChildTitleClicked"
        />
    </template>
</template>

<style scoped>
tr:hover {
    background-color: #f4f3f3; /* to help legibility */
}

.expand-button {
    background: none;
}

.title-button {
    background: none;
    padding: 0;
    text-align: left;
}
</style>
