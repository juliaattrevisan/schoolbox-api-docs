<script setup lang="ts">
/**
 * A single node row in the curriculum coverage report.
 */

import { hasCode } from "@schoolboxCurriculum/js/nodeUtils";
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
import {
    getFilteredTotalCount,
    getFilteredCourseColumnCount,
    getFilteredUnitColumnCount,
    getFilteredCourseCount,
} from "./util/countUtils";

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

// whether the node should be shown based on the filters
// is shown based on isVisible and whether disabled nodes should be shown
const showNode = window.Vue.computed(
    () =>
        (props.node.isVisible && filtersStore.showDisabled) ||
        (props.node.isVisible && !filtersStore.showDisabled && props.node.enabled),
);

// #region expand/collapse

// to remove any padding when node is at base depth and has no children
const isSingleNode = window.Vue.computed(
    () => props.depth === 0 && props.node.children && props.node.children.length === 0,
);

// show the expand button when the node has children, and onlyShowGaps is not active
const showExpandButton = window.Vue.computed(
    () => props.node.children && props.node.children.length > 0 && !filtersStore.onlyShowGaps,
);

// whether the cell can also be used to expand the node
const canExpandSelectCells = window.Vue.computed(
    () => props.node.children && props.node.children.length > 0 && !props.node.isExpanded && !filtersStore.onlyShowGaps,
);

// only shows when the node is not expanded and has no code, or when the total count is 0 and has code
// this is to prevent 0's showing up on nodes that can't actually have any usages assigned (those without a code)
const showZero = window.Vue.computed(
    () =>
        (!props.node.isExpanded && !hasCode(props.node)) ||
        (getFilteredTotalCount(props.node, !props.node.isExpanded) === 0 && hasCode(props.node)),
);

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
 * When a unit column is clicked, expand if there's children
 * and open the pop out if there's any direct usages
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
    // if there are no usages, don't open the pop out
    if (getFilteredUnitColumnCount(props.node, course, unit, false) === 0) {
        return;
    }
    popOutStore.setActiveUnitColumn(course, unit, props.node, buttonEl);
}

/**
 * When a course column is clicked, expand if there's children
 * and open the pop out if there's any direct usages
 * @param course - the course that was clicked
 * @param buttonEl - the button that was clicked
 */
function onCourseClicked(course: CourseWithCurriculumNodeUsage, buttonEl: HTMLButtonElement): void {
    // expand the node if it has children and is not expanded
    // this is so the usage count only shows the count for the node and not its children
    if (showExpandButton.value && !props.node.isExpanded) {
        nodesStore.toggleNodeExpand(props.node);
    }
    // if there are no usages, don't open the pop out
    if (getFilteredCourseColumnCount(props.node, course, false) === 0) {
        return;
    }
    popOutStore.setActiveCourseColumn(buttonEl);
}

// #endregion
</script>

<template>
    <tr v-if="showNode">
        <td
            class="l-flex l-flex--align-center l-gap-s f-no-margins name-cell u-border-right"
            :style="{
                paddingInlineStart: filtersStore.onlyShowGaps ? '0.5rem' : `calc(${props.depth * 0.5}rem + 0.5rem)`,
            }"
        >
            <button
                v-if="showExpandButton"
                class="l-flex l-flex--center l-flex__item--0-shrink f-no-bkg"
                :aria-label="props.node.isExpanded ? 'Collapse' : 'Expand'"
                :title="props.node.isExpanded ? 'Collapse' : 'Expand'"
                :data-test="`cc-report-table-node-expand-button-${props.node.id}`"
                @click="nodesStore.toggleNodeExpand(props.node)"
            >
                <sbx-icon v-if="props.node.isExpanded" name="up-arrow" />
                <sbx-icon v-else name="down-arrow" />
            </button>
            <!-- add paddingLeft to the last child node to align better with the parent above, which is showing the expand button -->
            <div
                class="l-flex l-flex--align-center l-gap-s"
                :style="{
                    paddingLeft: isSingleNode || filtersStore.onlyShowGaps || showExpandButton ? '0' : '2.5rem',
                }"
            >
                <sbx-icon v-if="!props.node.enabled" name="hidden" title="This outcome is disabled" />
                <button
                    class="l-pad--0 t-align-left f-no-bkg f-always-show"
                    :data-test="`cc-report-table-node-title-button-${props.node.id}`"
                    @click="emit('titleClick', props.node)"
                >
                    <template v-if="props.depth === 0 && !filtersStore.onlyShowGaps">
                        <b>{{ title }}</b>
                    </template>
                    <template v-else>{{ title }}</template>
                </button>
            </div>
        </td>
        <!-- add the total cell -->
        <!-- if there's no usages inside, we can jump straight to showing 0 if showZero allows -->
        <td v-if="props.node.hasNoUsages" class="u-border-left usage-cell">
            <button v-if="canExpandSelectCells" class="f-always-show usage-button" @click="onTotalClicked">
                <b v-if="showZero">0</b>
            </button>
            <b v-else-if="showZero">0</b>
        </td>
        <!-- or show the total filtered usage if it's above 0 and is selectable (has children within) -->
        <td v-else-if="canExpandSelectCells" class="u-border-left usage-cell">
            <button class="f-always-show usage-button" @click="onTotalClicked">
                <b v-if="getFilteredTotalCount(props.node, !props.node.isExpanded) > 0">{{
                    getFilteredTotalCount(props.node, !props.node.isExpanded)
                }}</b>
            </button>
        </td>
        <!-- otherwise show the total filtered usage if it's above 0 or showZero allows -->
        <td v-else class="u-border-left usage-cell">
            <b v-if="getFilteredTotalCount(props.node, false) > 0">{{ getFilteredTotalCount(props.node, false) }}</b>
            <b v-else-if="showZero">0</b>
        </td>
        <!-- loop through each course/unit, retrieving the node usage or returning 0 no usages -->
        <template v-for="course in props.node.usage.courses" :key="course.id">
            <!-- add the course cell -->
            <td v-if="props.node.hasNoUsages || course.hasNoCourseUsages" class="u-border-left"></td>
            <td
                v-else-if="getFilteredCourseColumnCount(props.node, course, !props.node.isExpanded) === 0"
                class="u-border-left usage-cell"
            ></td>
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
                    @click="(event) => onCourseClicked(course, event.target as HTMLButtonElement)"
                >
                    {{ getFilteredCourseColumnCount(props.node, course, !props.node.isExpanded) }}
                </button>
            </td>
            <!-- loop through the unit cells -->
            <template v-for="unit in course.units" :key="unit.id">
                <td v-if="props.node.hasNoUsages || unit.hasNoUsages"></td>
                <td
                    v-else-if="getFilteredUnitColumnCount(props.node, course, unit, !props.node.isExpanded) === 0"
                    class="usage-cell"
                ></td>
                <td v-else class="usage-cell">
                    <button
                        :id="`usage-button-${course.id}-${unit.id}-${props.node.id}`"
                        ref="buttonEl"
                        :style="{
                            background:
                                `usage-button-${course.id}-${unit.id}-${props.node.id}` === popOutStore.button?.id
                                    ? 'var(--accent-background)'
                                    : '',
                        }"
                        class="f-always-show usage-button"
                        :data-test="`cc-report-table-node-total-pop-out-button-${course.id}-${unit.id}-${props.node.id}`"
                        @click="(event) => onUnitClicked(course, unit, event.target as HTMLButtonElement)"
                    >
                        {{ getFilteredUnitColumnCount(props.node, course, unit, !props.node.isExpanded) }}
                    </button>
                </td>
            </template>
            <!-- add the total course cell if there's multiple courses -->
            <template v-if="useCoursesStore().hasMultipleCourses">
                <td v-if="props.node.hasNoUsages || course.hasNoTotalUsages"></td>
                <td v-else-if="canExpandSelectCells" class="usage-cell">
                    <button class="f-always-show usage-button" @click="onTotalClicked">
                        <template v-if="getFilteredCourseCount(props.node, course, !props.node.isExpanded) !== 0">{{
                            getFilteredCourseCount(props.node, course, !props.node.isExpanded)
                        }}</template>
                    </button>
                </td>
                <td v-else>
                    <template v-if="getFilteredCourseCount(props.node, course, !props.node.isExpanded) !== 0">{{
                        getFilteredCourseCount(props.node, course, !props.node.isExpanded)
                    }}</template>
                </td>
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

.name-cell {
    background-color: white;
    left: 0;
    position: sticky;
    z-index: calc(var(--sbx-depth-ui-sticky) - 1);
    &:hover {
        background-color: #f4f3f3; /* to help legibility */
    }
    @media (max-width: 1000px) {
        position: static; // prevent the cell from sticking on smaller screens
    }
}

.usage-cell {
    position: relative;
    &:has(.usage-button) {
        padding: 0; // padding is handled by the button instead
    }
}

.usage-button {
    background: none;
    display: flex; // to align the text to the top left
    height: 100%;
    margin: 0;
    padding: 0.625rem;
    position: absolute; // make the button fill the entire td cell
    text-align: left;
    width: 100%;
    &:hover {
        background: var(--accent-background);
    }
}
</style>
