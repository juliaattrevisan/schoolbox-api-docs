<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */

import locale from "@js/terminology/rosettaStone";
import { useTemplateRef } from "vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CcReportTableNode from "./cc-report-table-node.vue";
import CcReportTableNoNodes from "./cc-report-table-no-nodes.vue";
import CcUsagePopOut from "./cc-usage-pop-out.vue";
import { useReportStore } from "./store/ccReportStore";
import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";

const reportStore = useReportStore();
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
</script>

<template>
    <div class="scrollable">
        <table class="coverage-report">
            <thead>
                <tr>
                    <th rowspan="2" class="curriculum-column l-valign--bottom">Curriculum</th>
                    <th rowspan="2" class="u-border-left l-valign--bottom">Total</th>
                    <template v-for="course in reportStore.courses" :key="course.id">
                        <th class="u-border-left" style="border-right: none">Course</th>
                        <!-- colspan will be the number of units plus total if more than one course -->
                        <th
                            style="border-left: none"
                            :colspan="`${course.units && course.units.length ? (reportStore.hasMultipleCourses ? course.units.length + 1 : course.units.length) : 0}`"
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
                    <template v-for="course in reportStore.courses" :key="course.id">
                        <td class="u-border-left">
                            <a v-if="course.folder" :href="course.folder._links.view">{{ course.name }}</a
                            ><template v-else>{{ course.name }}</template>
                        </td>
                        <td v-for="unit in course.units" :key="unit.id">
                            <a v-if="unit.folder" :href="unit.folder._links.view">{{ unit.name }}</a
                            ><template v-else>{{ course.name }}</template>
                        </td>
                        <td v-if="reportStore.hasMultipleCourses" class="l-valign--bottom"><b>Total</b></td>
                    </template>
                </tr>
            </thead>
            <tbody ref="tbodyEl">
                <cc-report-table-node
                    v-for="node in reportStore.nodes"
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
        <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
    </basic-modal>
    <cc-usage-pop-out />
</template>

<style scoped>
.curriculum-column {
    min-width: 400px; /* fixed width for the curriculum column to prevent shifts on expand/collapse */
    width: 400px; /* fixed width for the curriculum column to prevent shifts on expand/collapse */
}
</style>
