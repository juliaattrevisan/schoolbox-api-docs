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

/**
 * To toggle the expanded state of the node
 */
function onTotalClicked(): void {
    if (!isExpanded.value) {
        isExpanded.value = true;
    }
}

// #endregion

// #region usage counts

const hasNoUsages = window.Vue.computed(() => getTotalCount(props.node, true) === 0);

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
    return getFilteredTotalCount(props.node, !isExpanded.value) === 0 && filtersStore.onlyShowGaps;
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
    if (showExpandButton.value && !isExpanded.value) {
        isExpanded.value = true;
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
    if (showExpandButton.value && !isExpanded.value) {
        isExpanded.value = true;
    }
    popOutStore.setActiveCourseColumn(buttonEl);
}

// #endregion
</script>

<template>
    <tr v-if="showNode" ref="nodeEl">
        <td class="l-flex l-flex--align-center l-gap-s f-no-margins" :style="{ paddingInlineStart: paddingStart }">
            <button
                v-if="showExpandButton"
                class="l-flex l-flex--center f-no-bkg"
                :aria-label="expandTitle"
                :title="expandTitle"
                @click="onExpandClicked"
            >
                <sbx-icon v-if="isExpanded" name="up-arrow" />
                <sbx-icon v-else name="down-arrow" />
            </button>
            <button
                class="l-pad--0 t-align-left f-no-bkg f-always-show"
                :style="{ paddingLeft: lastChildPadding }"
                @click="onTitleClicked"
            >
                {{ title }}
            </button>
        </td>
        <!-- if the node total and its children are completely empty, just hard set to 0 -->
        <td v-if="hasNoUsages"><b>0</b></td>
        <cc-report-table-node-total-column
            v-else
            :node="props.node"
            :is-expanded="isExpanded"
            @click="onTotalClicked"
        />
        <!-- loop through each course/unit, retrieving the node usage or returning 0 for undefined usages -->
        <template v-for="course in props.node.usage.courses" :key="course.id">
            <!-- if the node course column and its children are completely empty, just hard set to 0 -->
            <td v-if="hasNoUsages || getCourseColumnCount(props.node, course, true) === 0">0</td>
            <cc-report-table-node-course-column
                v-else
                :node="props.node"
                :course="course"
                :is-expanded="isExpanded"
                @click="onCourseClicked"
            />
            <template v-for="unit in course.units" :key="unit.id">
                <!-- if the node unit column and its children are completely empty, just hard set to 0 -->
                <td v-if="hasNoUsages || getUnitColumnCount(props.node, course, unit, true) === 0">0</td>
                <cc-report-table-node-unit-column
                    v-else
                    :node="props.node"
                    :course="course"
                    :unit="unit"
                    :is-expanded="isExpanded"
                    @click="onUnitClicked"
                />
            </template>
            <template v-if="coursesStore.hasMultipleCourses">
                <!-- if the node course and its children are completely empty, just hard set to 0 -->
                <td v-if="hasNoUsages || getCourseCount(props.node, course, true) === 0">0</td>
                <cc-report-table-node-course-total-column
                    v-else
                    :node="props.node"
                    :course="course"
                    :is-expanded="isExpanded"
                    @click="onTotalClicked"
                />
            </template>
        </template>
    </tr>
    <template v-if="hasChildren && (isExpanded || filtersStore.onlyShowGaps || reportStore.isPrinting)">
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
