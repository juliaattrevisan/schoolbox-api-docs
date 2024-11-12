<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */

import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CcReportTableNode from "./cc-report-table-node.vue";
import { useReportStore } from "./store/ccReportStore";
import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";

const reportStore = useReportStore();

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
</script>

<template>
    <div class="scrollable">
        <table class="coverage-report">
            <thead>
                <tr>
                    <th class="curriculum l-valign--bottom">Curriculum</th>
                    <th class="u-border-left l-valign--bottom">Total</th>
                    <template v-for="course in reportStore.courses" :key="course.id">
                        <td class="u-border-left"><b>Course:</b> {{ course.name }}</td>
                        <td v-for="unit in course.units" :key="unit.id" :class="``"><b>Unit:</b> {{ unit.name }}</td>
                        <td v-if="reportStore.hasMultipleCourses" :class="``"><b>Total</b></td>
                    </template>
                </tr>
            </thead>
            <tbody>
                <cc-report-table-node
                    v-for="node in reportStore.nodes"
                    :key="node.id"
                    :node="node"
                    :depth="0"
                    @title-click="onTitleClick"
                />
            </tbody>
        </table>
    </div>
    <basic-modal v-model:is-open="isDetailsOpen" :has-background-click="true">
        <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
    </basic-modal>
</template>

<style scoped>
table {
    border-left: 1px solid rgb(139, 139, 139);
    border-top: 1px solid rgb(139, 139, 139);
}

table thead tr td {
    font-weight: normal;
}

td,
th {
    border-bottom: 1px solid rgb(139, 139, 139);
    border-right: 1px solid rgb(139, 139, 139);
}

.curriculum {
    min-width: 400px; /* fixed width for the curriculum column to prevent shifts on expand/collapse */
    width: 400px; /* fixed width for the curriculum column to prevent shifts on expand/collapse */
}
</style>
