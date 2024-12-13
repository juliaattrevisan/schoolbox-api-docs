<script setup lang="ts">
/**
 * A single node row in the curriculum coverage report.
 */

import { useNodeTitle } from "@schoolboxCurriculum/vue/composables/useNodeTitle";
import type {
    CurriculumCoverageNode,
    CourseWithCurriculumNodeUsage,
    UnitWithCurriculumNodeUsage,
} from "../js/types/curriculumCoverageTypes";
import { useCoursesStore } from "./store/ccCoursesStore";
import { useNodesStore } from "./store/ccNodesStore";
import { useFiltersStore } from "./store/ccFiltersStore";
import { usePopOutStore } from "./store/ccPopOutStore";
import { getFilteredCourseColumnCount } from "./util/countUtils";
import CcReportTableNodeTotalColumn from "./cc-report-table-node-total-column.vue";
// import CcReportTableNodeCourseColumn from "./cc-report-table-node-course-column.vue";
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

const nodesStore = useNodesStore();
const filtersStore = useFiltersStore();
const { title } = useNodeTitle(props.node);

// #region expand/collapse

const showExpandButton = window.Vue.computed(() => props.node.children && !filtersStore.onlyShowGaps); // don't show the expand button when the node has no children, or onlyShowGaps is active

/**
 * To toggle the expanded state of the node
 */
function onTotalClicked(): void {
    if (!props.node.isExpanded) {
        nodesStore.toggleNodeExpand(props.node);
    }
}

// #endregion

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
    <tr v-if="props.node.isVisible">
        <!-- to gradually add more paddingStart to indicate node level depth -->
        <td
            class="l-flex l-flex--align-center l-gap-s f-no-margins"
            :style="{
                paddingInlineStart: filtersStore.onlyShowGaps ? '0.5rem' : `calc(${props.depth * 0.5}rem + 0.5rem)`,
            }"
        >
            <button
                v-if="showExpandButton"
                class="l-flex l-flex--center f-no-bkg"
                :aria-label="props.node.isExpanded ? 'Collapse' : 'Expand'"
                :title="props.node.isExpanded ? 'Collapse' : 'Expand'"
                @click="nodesStore.toggleNodeExpand(props.node)"
            >
                <sbx-icon v-if="props.node.isExpanded" name="up-arrow" />
                <sbx-icon v-else name="down-arrow" />
            </button>
            <!-- add paddingLeft to the last child node to align better with the expand button -->
            <button
                class="l-pad--0 t-align-left f-no-bkg f-always-show"
                :style="{
                    paddingLeft:
                        props.depth !== 0 && !props.node.children && !filtersStore.onlyShowGaps ? '2.75rem' : '0',
                }"
                @click="emit('titleClick', props.node)"
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
            <td v-if="props.node.hasNoUsages || course.hasNoCourseUsages">0</td>
            <td
                v-else-if="getFilteredCourseColumnCount(props.node, course, !props.node.isExpanded) === 0"
                class="u-border-left usage-cell"
            >
                {{ getFilteredCourseColumnCount(props.node, course, !props.node.isExpanded) }}
            </td>
            <td v-else class="u-border-left usage-cell">
                <button
                    :id="`usage-button-${course.id}-${props.node.id}`"
                    ref="buttonEl"
                    :style="{
                        background:
                            `usage-button-${course.id}-${props.node.id}` === popOutStore.button?.id
                                ? 'var(--accent-background)'
                                : '',
                    }"
                    class="f-always-show usage-button"
                    @click="(event) => onCourseClicked(event.target as HTMLButtonElement)"
                >
                    {{ getFilteredCourseColumnCount(props.node, course, !props.node.isExpanded) }}
                </button>
            </td>
            <template v-for="unit in course.units" :key="unit.id">
                <!-- if the node unit column and its children are completely empty, just hard set to 0 -->
                <td v-if="props.node.hasNoUsages || unit.hasNoUsages">0</td>
                <cc-report-table-node-unit-column
                    v-else
                    :node="props.node"
                    :course="course"
                    :unit="unit"
                    :is-expanded="props.node.isExpanded"
                    @click="onUnitClicked"
                />
            </template>
            <template v-if="useCoursesStore().hasMultipleCourses">
                <!-- if the node course and its children are completely empty, just hard set to 0 -->
                <td v-if="props.node.hasNoUsages || course.hasNoTotalUsages">0</td>
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
    <template v-if="props.node.children">
        <cc-report-table-node
            v-for="child in props.node.children"
            :key="child.id"
            :node="child"
            :depth="props.depth + 1"
            @title-click="(node) => emit('titleClick', node)"
        />
    </template>
</template>

<style lang="scss" scoped>
tr:hover {
    background-color: #f4f3f3; /* to help legibility */
}

.usage-cell {
    position: relative;
}

.usage-cell:has(.usage-button) {
    padding: 0;
}

.usage-button {
    background: none;
    display: flex; // to align the text to the top left
    padding: 0.625rem;
    position: absolute; // make the button fill the entire td cell
    margin: 0;
    height: 100%;
    text-align: left;
    width: 100%;
    &:hover {
        background: var(--accent-background);
    }
}
</style>
