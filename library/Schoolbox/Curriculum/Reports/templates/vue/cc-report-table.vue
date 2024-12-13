<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */

import { useTemplateRef, toRefs } from "vue";
import { useScroll } from "@vueuse/core";
import locale from "@js/terminology/rosettaStone";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CcReportTableNode from "./cc-report-table-node.vue";
import CcReportTableNoNodes from "./cc-report-table-no-nodes.vue";
import CcUsagePopOut from "./cc-usage-pop-out.vue";
import { useCoursesStore } from "./store/ccCoursesStore";
import { useNodesStore } from "./store/ccNodesStore";
import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";

const coursesStore = useCoursesStore();
const nodesStore = useNodesStore();
const isDetailsOpen = window.Vue.ref(false);
const selectedNodeId = window.Vue.ref<number | null>(null);
const tbodyEl = useTemplateRef<HTMLTableSectionElement>("tbodyEl");

/**
 * When a title is selected, show the details modal
 * @param node - the node that was selected
 */
function onTitleClick(node: CurriculumCoverageNode): void {
    selectedNodeId.value = node.id;
    isDetailsOpen.value = true;
}

/**
 * Unset the selected node when the details modal is closed
 */
window.Vue.watch(isDetailsOpen, (isOpen) => {
    if (!isOpen) {
        selectedNodeId.value = null;
    }
});

// #region scrollable height

const maxScrollableHeight = window.Vue.ref<string>(`${window.innerHeight - 100}px`); // 100px chosen to give some breathing room

/**
 * Set the max height to allow the thead to use sticky position
 * and to incrementally display large row lists on scroll
 */
function setScrollableHeight(): void {
    maxScrollableHeight.value = `${window.innerHeight - 100}px`;
}

/**
 * Start setting the scrollable height on any window resize change
 */
window.Vue.onMounted(() => {
    setScrollableHeight();
    window.addEventListener("resize", () => {
        setScrollableHeight();
    });
});

// #endregion

const scrollableEl = useTemplateRef<HTMLDivElement>("scrollableEl");

/**
 * To display more nodes when there's lots to display and the user has scrolled to the bottom
 */
const { arrivedState } = useScroll(scrollableEl);
const { bottom } = toRefs(arrivedState);
window.Vue.watch(bottom, (isAtBottom) => {
    // if (isAtBottom && currentHeight.value === maxResultsHeight - 2) {
    if (isAtBottom && nodesStore.hasMore) {
        nodesStore.showMore();
        console.warn("show more");
    }
});
</script>

<template>
    <div ref="scrollableEl" class="scrollable" :style="{ maxHeight: maxScrollableHeight }">
        <table class="coverage-report">
            <thead>
                <tr>
                    <th rowspan="2" class="curriculum-column l-valign--bottom">Curriculum</th>
                    <th rowspan="2" class="u-border-left l-valign--bottom">Total</th>
                    <template v-for="course in coursesStore.courses" :key="course.id">
                        <th class="u-border-left" style="border-right: none">Course</th>
                        <!-- colspan will be the number of units plus total if more than one course -->
                        <th
                            style="border-left: none"
                            :colspan="`${course.units && course.units.length ? (coursesStore.hasMultipleCourses ? course.units.length + 1 : course.units.length) : 0}`"
                        >
                            {{
                                course.units && course.units.length > 1
                                    ? locale.unit.properPlural()
                                    : locale.unit.proper()
                            }}
                        </th>
                    </template>
                </tr>
                <tr>
                    <template v-for="course in coursesStore.courses" :key="course.id">
                        <td class="u-border-left course-column">
                            <a v-if="course.folder" :href="course.folder._links.view">{{ course.name }}</a
                            ><template v-else>{{ course.name }}</template>
                        </td>
                        <td v-for="unit in course.units" :key="unit.id" class="unit-column">
                            <a v-if="unit.folder" :href="unit.folder._links.view">{{ unit.name }}</a
                            ><template v-else>{{ course.name }}</template>
                        </td>
                        <td v-if="coursesStore.hasMultipleCourses" class="l-valign--bottom"><b>Total</b></td>
                    </template>
                </tr>
            </thead>
            <tbody ref="tbodyEl">
                <cc-report-table-node
                    v-for="node in nodesStore.nodes"
                    :key="node.id"
                    :node="node"
                    :depth="0"
                    @title-click="onTitleClick"
                />
                <cc-report-table-no-nodes :tbody-el="tbodyEl" />
            </tbody>
        </table>
    </div>
    <basic-modal v-model:is-open="isDetailsOpen" :has-background-click="true">
        <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" :include-usage="true" />
    </basic-modal>
    <cc-usage-pop-out />
</template>

<style scoped>
thead {
    /** fix the head to stay at the top when scrolling */
    position: sticky;
    top: 0;
    z-index: var(--sbx-depth-ui-sticky);
}

.course-column,
.unit-column {
    min-width: 175px; /* prevent the course/unit names from getting too small */
}

.curriculum-column {
    min-width: 400px; /* fixed width for the curriculum column to prevent shifts on expand/collapse */
    width: 400px; /* fixed width for the curriculum column to prevent shifts on expand/collapse */
}
</style>
