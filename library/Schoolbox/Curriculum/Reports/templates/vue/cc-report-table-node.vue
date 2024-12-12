<script setup lang="ts">
/**
 * A single node row in the curriculum coverage report.
 */

import { useTemplateRef } from "vue";
import { gsap } from "gsap/all";
import { useNodeTitle } from "@schoolboxCurriculum/vue/composables/useNodeTitle";
import type {
    CurriculumCoverageNode,
    CourseWithCurriculumNodeUsage,
    UnitWithCurriculumNodeUsage,
} from "../js/types/curriculumCoverageTypes";
import { useReportStore } from "./store/ccReportStore";
import { useCoursesStore } from "./store/ccCoursesStore";
import { useNodesStore } from "./store/ccNodesStore";
import { useFiltersStore } from "./store/ccFiltersStore";
import { usePopOutStore } from "./store/ccPopOutStore";
import {
    getTotalCount,
    getCourseColumnCount,
    getUnitColumnCount,
    getCourseCount,
    getFilteredTotalCount,
} from "./util/countUtils";
import CcReportTableNodeTotalColumn from "./cc-report-table-node-total-column.vue";
import CcReportTableNodeCourseColumn from "./cc-report-table-node-course-column.vue";
import CcReportTableNodeUnitColumn from "./cc-report-table-node-unit-column.vue";
import CcReportTableNodeCourseTotalColumn from "./cc-report-table-node-course-total-column.vue";

interface Props {
    node: CurriculumCoverageNode;
    depth: number; // The depth of the node in the tree
}
const props = defineProps<Props>();

const emit = defineEmits<{
    titleClick: [node: CurriculumCoverageNode];
}>();

const reportStore = useReportStore();
const coursesStore = useCoursesStore();
const nodesStore = useNodesStore();
const filtersStore = useFiltersStore();
const { title } = useNodeTitle(props.node);
const hasChildren = window.Vue.ref(Array.isArray(props.node.children) && props.node.children.length > 0);
const paddingStart = window.Vue.computed(() =>
    // to gradually add more paddingStart to indicate node level depth
    filtersStore.onlyShowGaps ? "0.5rem" : `calc(${props.depth * 0.5}rem + 0.5rem)`,
);
const lastChildPadding = window.Vue.computed(() =>
    // to add padding to the last child node to align better with the expand button
    props.depth !== 0 && !hasChildren.value && !filtersStore.onlyShowGaps ? "2.75rem" : "0",
);

// #region expand/collapse

const expandTitle = window.Vue.computed(() => (props.node.isExpanded ? "Collapse" : "Expand"));
const showExpandButton = window.Vue.computed(() => hasChildren.value && !filtersStore.onlyShowGaps); // don't show the expand button when the node has no children, or onlyShowGaps is active

/**
 * To toggle the expanded state of the node
 */
function onExpandClicked(): void {
    nodesStore.toggleNodeExpand(props.node);
}

/**
 * To toggle the expanded state of the node
 */
function onTotalClicked(): void {
    if (!props.node.isExpanded) {
        nodesStore.toggleNodeExpand(props.node);
    }
}

// #endregion

/**
 * Fade in when shown to help provide context
 */
const nodeEl = useTemplateRef<HTMLElement | null>("nodeEl");
window.Vue.onMounted(async () => {
    // expand the node if it has no children
    if (props.node.isVisible) {
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

// #region unit selection, open pop out

const popOutStore = usePopOutStore();

/**
 * When a unit column is clicked, open the pop out
 * @param course - the course that was clicked
 * @param unit - the unit that was clicked
 * @param buttonEl - the button that was clicked
 */
function onUnitClicked(
    course: CourseWithCurriculumNodeUsage,
    unit: UnitWithCurriculumNodeUsage,
    buttonEl: HTMLButtonElement,
): void {
    // expand the node if it has children and is not expanded
    // this is so the usage count only shows the count for the node and not its children
    if (showExpandButton.value && !props.node.isExpanded) {
        nodesStore.toggleNodeExpand(props.node);
    }
    popOutStore.setActiveUnitColumn(course, unit, props.node, buttonEl);
}

/**
 * When a course column is clicked, open the pop out
 * @param buttonEl - the button that was clicked
 */
function onCourseClicked(buttonEl: HTMLButtonElement): void {
    // expand the node if it has children and is not expanded
    // this is so the usage count only shows the count for the node and not its children
    if (showExpandButton.value && !props.node.isExpanded) {
        nodesStore.toggleNodeExpand(props.node);
    }
    popOutStore.setActiveCourseColumn(buttonEl);
}

// #endregion
</script>

<template>
    <!-- <tr v-if="showNode" ref="nodeEl"> -->
    <tr v-if="props.node.isVisible" ref="nodeEl">
        <td class="l-flex l-flex--align-center l-gap-s f-no-margins" :style="{ paddingInlineStart: paddingStart }">
            <button
                v-if="showExpandButton"
                class="l-flex l-flex--center f-no-bkg"
                :aria-label="expandTitle"
                :title="expandTitle"
                @click="onExpandClicked"
            >
                <sbx-icon v-if="props.node.isExpanded" name="up-arrow" />
                <sbx-icon v-else name="down-arrow" />
            </button>
            <button
                class="l-pad--0 t-align-left f-no-bkg f-always-show"
                :style="{ paddingLeft: lastChildPadding }"
                @click="onTitleClicked"
            >
                <!-- bolden the selected curriculum node to help distinguish -->
                <template v-if="props.depth === 0"
                    ><b>{{ title }}</b></template
                >
                <template v-else>{{ title }}</template>
            </button>
        </td>
        <!-- if the node total and its children are completely empty, just hard set to 0 -->
        <td v-if="props.node.hasNoUsages"><b>0</b></td>
        <cc-report-table-node-total-column
            v-else
            :node="props.node"
            :is-expanded="props.node.isExpanded"
            @click="onTotalClicked"
        />
        <!-- loop through each course/unit, retrieving the node usage or returning 0 for undefined usages -->
        <template v-for="course in props.node.usage.courses" :key="course.id">
            <!-- if the node course column and its children are completely empty, just hard set to 0 -->
            <td v-if="props.node.hasNoUsages || course.hasNoUsages">0</td>
            <cc-report-table-node-course-column
                v-else
                :node="props.node"
                :course="course"
                :is-expanded="props.node.isExpanded"
                @click="onCourseClicked"
            />
            <template v-for="unit in course.units" :key="unit.id">
                <!-- if the node unit column and its children are completely empty, just hard set to 0 -->
                <td v-if="props.node.hasNoUsages || getUnitColumnCount(props.node, course, unit, true) === 0">0</td>
                <cc-report-table-node-unit-column
                    v-else
                    :node="props.node"
                    :course="course"
                    :unit="unit"
                    :is-expanded="props.node.isExpanded"
                    @click="onUnitClicked"
                />
            </template>
            <template v-if="coursesStore.hasMultipleCourses">
                <!-- if the node course and its children are completely empty, just hard set to 0 -->
                <td v-if="props.node.hasNoUsages || getCourseCount(props.node, course, true) === 0">0</td>
                <cc-report-table-node-course-total-column
                    v-else
                    :node="props.node"
                    :course="course"
                    :is-expanded="props.node.isExpanded"
                    @click="onTotalClicked"
                />
            </template>
        </template>
    </tr>
    <!-- <template v-if="hasChildren && (isExpanded || filtersStore.onlyShowGaps || reportStore.isPrinting)"> -->
    <template v-if="hasChildren">
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
</style>
