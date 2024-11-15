<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */

import { useTemplateRef } from "vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CcReportTableNode from "./cc-report-table-node.vue";
import CcUsagePopOut from "./cc-usage-pop-out.vue";
import { useReportStore } from "./store/ccReportStore";
import { useFiltersStore } from "./store/ccFiltersStore";
import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";

const reportStore = useReportStore();
const filtersStore = useFiltersStore();
const isDetailsOpen = window.Vue.ref(false);
const selectedNodeId = window.Vue.ref<number | null>(null);

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

const tbodyEl = useTemplateRef<HTMLTableSectionElement>("tbodyEl");
const showEmpty = window.Vue.ref(false);

window.Vue.onMounted(() => {
    const lrnObserver = new MutationObserver((mutationsList) => {
        if (!filtersStore.onlyShowGaps) {
            showEmpty.value = false;
            return;
        }
        mutationsList.forEach(() => {
            if (!showEmpty.value && tbodyEl.value?.children && tbodyEl.value.children.length === 0) {
                console.warn(tbodyEl.value.children.length);
                showEmpty.value = true;
            }
        });
    });
    lrnObserver.observe(tbodyEl.value as HTMLTableSectionElement, {
        subtree: false,
        childList: true,
        attributes: false,
    });
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
                            {{ course.units && course.units.length > 1 ? "Units" : "Unit" }}
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
                <tr v-if="showEmpty">
                    <td :colspan="reportStore.totalColumnCount" class="t-align-center">
                        No curriculum outcomes have a total usage count of 0
                    </td>
                </tr>
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
